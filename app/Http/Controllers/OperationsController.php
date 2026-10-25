<?php

namespace App\Http\Controllers;

use App\Domain\Orders\Actions\TransitionOrderStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationsController extends Controller
{
    use Concerns\ChecksTokenAbilities;

    public function index(Request $request, string $location)
    {
        $this->ability($request, 'orders:read');
        $context = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireOperate($request->user(), $context);
        $query = Order::where('organization_id', $context->organization_id)->where('location_id', $context->id)->with(['items.modifiers', 'history']);
        if ($request->boolean('active')) {
            $query->whereNotIn('status', ['completed', 'rejected', 'cancelled']);
        }

        return OrderResource::collection($query->latest('id')->paginate(100));
    }

    public function transition(Request $request, string $order, TransitionOrderStatus $action)
    {
        $this->ability($request, 'orders:manage');
        $data = $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)], 'version' => ['required', 'integer', 'min:1'], 'reason' => ['nullable', 'string', 'max:500']]);
        $result = $action->execute($request->user(), Order::where('public_id', $order)->firstOrFail(), OrderStatus::from($data['status']), $data['version'], $data['reason'] ?? null);

        return new OrderResource($result->load(['items.modifiers', 'history']));
    }
}
