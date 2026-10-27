<?php

namespace App\Http\Controllers;

use App\Domain\Dining\OrderingWindow;
use App\Domain\Dining\TableSessions;
use App\Domain\Tenancy\TenantAccess;
use App\Models\DiningTable;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DiningController extends Controller
{
    public function manage(Request $request, string $location, WorkspaceController $workspace)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireFeature($request->user(), $context, 'tables');
        $manager = app(TenantAccess::class)->canManage($request->user(), $context->organization_id);
        $tables = DiningTable::where('location_id', $context->id)->where('is_active', true)->with(['sessions' => fn ($q) => $q->whereNull('closed_at')->where('expires_at', '>', now())])->get()->map(fn ($table) => [...$table->toArray(), 'qr_url' => app(TenantAccess::class)->canFeature($request->user(), $context, 'tables') ? rtrim(config('app.url'), '/').'/table/'.$table->public_id.'#token='.Crypt::decryptString($table->qr_token_encrypted) : null]);
        Inertia::encryptHistory();

        return Inertia::render('restaurant/Tables', ['orderingWindow' => app(OrderingWindow::class)->status($context), 'staffActivation' => $context->setup_profile['services']['qr_requires_staff'] ?? false, 'location' => $context->load('restaurant', 'hours', 'fulfillment', 'zones'), 'locations' => $workspace->locations($request), 'canManage' => $manager, 'tables' => $tables])->toResponse($request)->header('Cache-Control', 'no-store');
    }

    public function bulk(Request $request, string $location, TableSessions $sessions)
    {
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireFeature($request->user(), $context, 'tables', true);
        $data = $request->validate(['operation' => 'required|in:open,close', 'scope' => 'required|in:all,area', 'area' => 'required_if:scope,area|nullable|string|max:80']);
        $sessions->bulk($request->user(), $context, $data['operation'], $data['scope'] === 'area' ? $data['area'] : null);

        return back()->with('success', __('Table updated.'));
    }

    public function operation(Request $request, string $table, string $operation, TableSessions $sessions)
    {
        $context = DiningTable::where('public_id', $table)->firstOrFail();
        match ($operation) {
            'open' => $sessions->open($context, $request->user()), 'close' => $sessions->close($request->user(), $context), 'rotate' => $sessions->rotate($request->user(), $context), default => abort(404)
        };

        return back()->with('success', __('Table updated.'));
    }

    private function publicTable(Request $request, string $id): DiningTable
    {
        $table = DiningTable::where('public_id', $id)->where('is_active', true)->firstOrFail();
        $mapped = $request->attributes->get('storefront_location');
        abort_if($mapped && (int) $mapped !== $table->location_id, 404);

        return $table;
    }

    public function menu(Request $request, string $table, PublicOrderingController $ordering)
    {
        $context = $this->publicTable($request, $table);

        $window = app(OrderingWindow::class)->status($context->location);
        if (! $context->location->fulfillment?->dine_in_enabled || $context->ordering_paused || ! $window['is_open']) {
            return Inertia::render('storefront/TableUnavailable', ['restaurant' => $context->location->restaurant->only('name', 'slug', 'primary_color', 'logo_path'), 'orderingWindow' => $window, 'reason' => ! $context->location->fulfillment?->dine_in_enabled ? 'service_disabled' : ($context->ordering_paused ? 'table_closed' : $window['reason']), 'menuUrl' => $request->attributes->get('storefront_location') ? '/' : $context->location->storefront_path])->toResponse($request)->header('Cache-Control', 'no-store');
        }

        return Inertia::render('storefront/Menu', [...$ordering->data($context->location), 'tableContext' => $context->only('public_id', 'code', 'area', 'ordering_paused')]);
    }

    public function connect(Request $request, string $table, TableSessions $sessions)
    {
        $data = $request->validate(['qr_token' => 'required|string|size:64']);
        $context = $this->publicTable($request, $table);
        if (! $context->location->fulfillment?->dine_in_enabled) {
            throw ValidationException::withMessages(['table' => __('Table ordering is disabled at this location. Please contact the restaurant team.')]);
        }
        $session = $sessions->open($context, null, $data['qr_token']);

        return response()->json(['session_id' => $session->public_id, 'token' => Crypt::decryptString($session->token_encrypted), 'expires_at' => $session->expires_at])->header('Cache-Control', 'no-store');
    }
}
