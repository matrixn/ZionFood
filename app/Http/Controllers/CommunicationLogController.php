<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantAccess;
use App\Models\CommunicationLog;
use App\Models\Restaurant;
use App\Models\RestaurantLocation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Sanctum\TransientToken;

class CommunicationLogController extends Controller
{
    public function index(Request $r, ?string $location = null)
    {
        $data = $this->data($r, $location);
        $props = $location ? app(WorkspaceController::class)->workspaceProps($r, $location, 'settings') : [];

        return Inertia::render('CommunicationLogs', [...$props, 'initial' => $data, 'platform' => $location === null]);
    }

    public function data(Request $r, ?string $location = null): array
    {
        $token = $r->user()->currentAccessToken();
        abort_if($token && ! $token instanceof TransientToken, 403);
        $l = null;
        if ($location) {
            $l = RestaurantLocation::where('public_id', $location)->firstOrFail();
            app(TenantAccess::class)->requireManage($r->user(), $l->organization_id);
            abort_unless($l->restaurant->communication_logs_enabled, 403);
        } else {
            abort_unless($r->user()->is_platform_admin, 403);
        }
        $d = $r->validate(['restaurant' => 'nullable|ulid', 'direction' => 'nullable|in:inbound,outbound', 'status' => 'nullable|integer|between:100,599', 'outcome' => 'nullable|in:success,client_error,server_error,delivered,retrying,failed,blocked', 'request_id' => 'nullable|uuid', 'event_id' => 'nullable|ulid', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'page' => 'nullable|integer|between:1,100000']);
        $q = CommunicationLog::query()->with('restaurant:id,name,public_id', 'location:id,name', 'connection:id,name');
        if ($l) {
            $q->where('restaurant_id', $l->restaurant_id)->where('organization_id', $l->organization_id);
        } elseif (! empty($d['restaurant'])) {
            $q->where('restaurant_id', Restaurant::where('public_id', $d['restaurant'])->value('id') ?? 0);
        }
        foreach (['direction', 'outcome', 'request_id', 'event_id'] as $key) {
            if (filled($d[$key] ?? null)) {
                $q->where($key, $d[$key]);
            }
        }
        if (! empty($d['status'])) {
            $q->where('http_status', $d['status']);
        }
        if (! empty($d['from'])) {
            $q->where('created_at', '>=', $d['from'].' 00:00:00');
        }
        if (! empty($d['to'])) {
            $q->where('created_at', '<', CarbonImmutable::parse($d['to'], 'UTC')->addDay());
        }
        $page = $q->latest('id')->paginate(30);
        $page->getCollection()->each(fn ($log) => $log->location?->setAppends([]));

        return ['logs' => $page, 'restaurants' => $l ? [] : Restaurant::orderBy('name')->get(['public_id', 'name']), 'retention_days' => config('wordpress.log_retention_days')];
    }
}
