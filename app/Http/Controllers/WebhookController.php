<?php

namespace App\Http\Controllers;

use App\Domain\Integrations\ManageWebhook;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Requests\WebhookRequest;
use App\Jobs\DeliverWebhook;
use App\Models\RestaurantLocation;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\TransientToken;

class WebhookController extends Controller
{
    private function context(Request $request, string $location): RestaurantLocation
    {
        $token = $request->user()->currentAccessToken();
        abort_if($token && ! $token instanceof TransientToken, 403);
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireManage($request->user(), $context->organization_id);

        return $context;
    }

    public function index(Request $request, string $location)
    {
        $context = $this->context($request, $location);
        $endpoints = WebhookEndpoint::where('organization_id', $context->organization_id)->where('location_id', $context->id)->get();

        return response()->json(['endpoints' => $endpoints, 'deliveries' => WebhookDelivery::whereIn('endpoint_id', WebhookEndpoint::withTrashed()->where('organization_id', $context->organization_id)->where('location_id', $context->id)->pluck('id'))->with('endpoint:id,name,deleted_at')->latest('id')->paginate(30, ['id', 'endpoint_id', 'event_id', 'status', 'attempts', 'http_status', 'failure_code', 'created_at', 'delivered_at', 'next_attempt_at', 'locked_until'])])->header('Cache-Control', 'no-store');
    }

    public function store(WebhookRequest $request, string $location, ManageWebhook $action)
    {
        $context = $this->context($request, $location);

        return response()->json($action->execute($request->user(), $context, $request->validated()), 201);
    }

    public function update(WebhookRequest $request, string $location, int $endpoint, ManageWebhook $action)
    {
        $context = $this->context($request, $location);
        $record = WebhookEndpoint::where('organization_id', $context->organization_id)->where('location_id', $context->id)->findOrFail($endpoint);

        return response()->json($action->execute($request->user(), $context, $request->validated(), $record));
    }

    public function destroy(Request $request, string $location, int $endpoint)
    {
        $context = $this->context($request, $location);
        DB::transaction(function () use ($context, $endpoint) {
            $record = WebhookEndpoint::where('organization_id', $context->organization_id)->where('location_id', $context->id)->findOrFail($endpoint);
            $record->update(['enabled' => false]);
            $record->delete();
            WebhookDelivery::where('endpoint_id', $record->id)->whereIn('status', ['pending', 'retrying'])->update(['status' => 'cancelled', 'next_attempt_at' => null]);
        });

        return response()->noContent();
    }

    public function retry(Request $request, string $location, int $delivery)
    {
        $context = $this->context($request, $location);
        DB::transaction(function () use ($context, $delivery) {
            $endpoints = WebhookEndpoint::where('organization_id', $context->organization_id)->where('location_id', $context->id)->where('enabled', true)->pluck('id');
            $record = WebhookDelivery::whereIn('endpoint_id', $endpoints)->whereKey($delivery)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($record->status, ['failed', 'blocked', 'retrying'], true), 409);
            $record->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(), 'locked_until' => null, 'claim_token' => null]);
            DB::afterCommit(fn () => DeliverWebhook::dispatch($record->id));
        });

        return response()->noContent();
    }
}
