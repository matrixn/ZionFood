<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\Actions\CaptureServiceLead;
use App\Domain\Marketing\Actions\ManageServiceLead;
use App\Models\ServiceLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ServiceLeadController extends Controller
{
    public function store(Request $request, CaptureServiceLead $action)
    {
        abort_unless(! $request->attributes->has('storefront_location') && in_array($request->getHost(), config('zionfood.app_hosts'), true), 404);
        abort_unless($request->expectsJson(), 406);
        if (in_array($request->input('locale'), ['ro', 'en'], true)) {
            app()->setLocale($request->input('locale'));
        }
        $action->execute($request->only('name', 'email', 'phone', 'locale', 'website'));

        return response()->json(['status' => 'received'])->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $filters = $request->validate(['status' => ['nullable', Rule::in(ServiceLead::STATUSES)], 'page' => 'nullable|integer|min:1|max:100000']);
        $query = ServiceLead::query();
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        Inertia::encryptHistory();
        $leads = $query->latest('id')->paginate(25)->through(fn ($lead) => [
            ...$lead->only('public_id', 'status', 'locale', 'created_at', 'updated_at'),
            'name' => $lead->name, 'email' => $lead->email, 'phone' => $lead->phone,
        ]);

        return Inertia::render('admin/Leads', ['leads' => $leads, 'filters' => $filters, 'summary' => ServiceLead::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status')])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $lead, ManageServiceLead $action)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $action->update($request->user(), ServiceLead::where('public_id', $lead)->firstOrFail(), $request->input('status'));

        return response()->noContent();
    }

    public function destroy(Request $request, string $lead, ManageServiceLead $action)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $action->delete($request->user(), ServiceLead::where('public_id', $lead)->firstOrFail());

        return response()->noContent();
    }
}
