<?php

namespace App\Http\Controllers;

use App\Domain\Platform\Actions\BuildPlatformOverview;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PlatformController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_platform_admin, 403);
        Inertia::encryptHistory();
    }

    public function dashboard(Request $request, BuildPlatformOverview $overview)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['days' => ['sometimes', 'integer', 'in:7,30,90']]);

        return Inertia::render('admin/Dashboard', ['overview' => $overview->execute($request->user(), (int) ($data['days'] ?? 30))])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function organizations(Request $request)
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'subscription' => ['nullable', 'in:trial,expired,other']]);
        $query = Organization::query()->withCount('restaurants', 'memberships');
        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$this->pattern($search)])->orWhere('public_id', $search));
        }
        if ($status = $filters['subscription'] ?? null) {
            $query->whereIn('id', OrganizationSubscription::query()->select('organization_id')->when($status === 'trial', fn ($q) => $q->where('status', 'trial')->where('trial_ends_at', '>', now()))->when($status === 'expired', fn ($q) => $q->where('status', 'trial')->where('trial_ends_at', '<=', now()))->when($status === 'other', fn ($q) => $q->where('status', '!=', 'trial')));
        }
        $rows = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $subscriptions = OrganizationSubscription::with('plan:id,name')->whereIn('organization_id', $rows->pluck('id'))->get()->keyBy('organization_id');
        $rows->through(fn ($o) => [...$o->only('public_id', 'name', 'created_at', 'restaurants_count', 'memberships_count'), 'subscription' => $this->subscription($subscriptions->get($o->id))]);

        return Inertia::render('admin/Organizations', ['organizations' => $rows, 'filters' => $filters])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function organization(Request $request, string $organization)
    {
        $this->authorizeAdmin($request);
        $o = Organization::where('public_id', $organization)->with('restaurants.locations', 'memberships.user:id,name,email,email_verified_at')->firstOrFail();

        return Inertia::render('admin/Organization', [
            'organization' => [...$o->only('public_id', 'name', 'created_at'), 'subscription' => $this->subscription(OrganizationSubscription::with('plan')->where('organization_id', $o->id)->first()),
                'restaurants' => $o->restaurants->map(fn ($r) => [...$r->only('public_id', 'name'), 'locations' => $r->locations->map(fn ($l) => $l->only('public_id', 'name', 'address', 'timezone', 'accepting_orders'))]),
                'members' => $o->memberships->map(fn ($m) => ['role' => $m->role, 'name' => $m->user?->name, 'email' => $m->user?->email, 'verified' => (bool) $m->user?->email_verified_at])],
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function users(Request $request)
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'access' => ['nullable', 'in:admin,unverified,verified']]);
        $query = User::query()->select('id', 'name', 'email', 'email_verified_at', 'is_platform_admin', 'created_at')->addSelect(['memberships_count' => DB::table('organization_memberships')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id')]);
        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$this->pattern($search)])->orWhereRaw("email LIKE ? ESCAPE '!'", [$this->pattern($search)]));
        }
        $query->when(($filters['access'] ?? '') === 'admin', fn ($q) => $q->where('is_platform_admin', true))->when(($filters['access'] ?? '') === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))->when(($filters['access'] ?? '') === 'verified', fn ($q) => $q->whereNotNull('email_verified_at'));

        return Inertia::render('admin/Users', ['users' => $query->orderByDesc('id')->paginate(20)->withQueryString(), 'filters' => $filters])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function operations(Request $request, BuildPlatformOverview $overview)
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate(['status' => ['nullable', 'in:queued,enriching,ready,published,reverted,failed']]);
        $imports = DB::table('import_batches as b')->join('restaurant_locations as l', 'l.id', '=', 'b.location_id')->join('organizations as o', 'o.id', '=', 'b.organization_id')->select('b.public_id', 'b.status', 'b.provider', 'b.created_at', 'l.name as location', 'o.name as organization')->when($filters['status'] ?? null, fn ($q, $s) => $q->where('b.status', $s))->orderByDesc('b.id')->paginate(15)->withQueryString();

        return Inertia::render('admin/Operations', [
            'attention' => $overview->attention(), 'imports' => $imports, 'filters' => $filters,
            'connections' => ['wordpress' => DB::table('wordpress_connections')->where('active', true)->count(), 'webhooks' => DB::table('webhook_endpoints')->where('enabled', true)->whereNull('deleted_at')->count(), 'pending_outbox' => DB::table('outbox_messages')->whereNull('delivered_at')->whereNull('failed_at')->count()],
            'mail' => DB::table('mail_provider_limits')->where('provider', 'resend')->first(['daily_limit', 'paused_until', 'pause_reason', 'usage_checked_at']),
            'alerts' => DB::table('email_alert_settings')->where('id', 1)->first(['enabled', 'suspended_at']),
            'updatedAt' => now()->toIso8601String(),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function legacy(Request $request, string $organization)
    {
        $this->authorizeAdmin($request);
        Organization::where('public_id', $organization)->firstOrFail();

        return redirect('/admin/organizations/'.$organization);
    }

    private function subscription(?OrganizationSubscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        return ['plan' => $subscription->plan?->name, 'status' => $subscription->status, 'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(), 'expired' => $subscription->status === 'trial' && $subscription->trial_ends_at?->isPast()];
    }

    private function pattern(string $search): string
    {
        // Treat wildcard characters as literals across SQLite and MariaDB.
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
    }
}
