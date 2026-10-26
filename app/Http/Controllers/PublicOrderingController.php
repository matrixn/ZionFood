<?php

namespace App\Http\Controllers;

use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicOrderingController extends Controller
{
    public function resolve(Request $request, string $publicId): RestaurantLocation
    {
        $location = RestaurantLocation::where('public_id', $publicId)->firstOrFail();
        $mapped = $request->attributes->get('storefront_location');
        abort_if($mapped && (int) $mapped !== $location->id, 404);

        return $location;
    }

    public function data(RestaurantLocation $location): array
    {
        return ['mapConfig' => config('maps'), 'mapCenter' => isset($location->setup_profile['basics']['latitude'], $location->setup_profile['basics']['longitude']) ? [(float) $location->setup_profile['basics']['latitude'], (float) $location->setup_profile['basics']['longitude']] : [44.4268, 26.1025], 'location' => $location->only('public_id', 'name', 'address', 'currency', 'accepting_orders', 'timezone'), 'restaurant' => $location->restaurant->only('name', 'slug', 'description', 'tagline', 'primary_color', 'logo_path', 'cover_path', 'contact_email', 'contact_phone', 'website'),
            'menus' => Cache::remember('public-menu:'.$location->public_id, 30, fn () => $location->menus()->where('is_active', true)->with(['categories' => fn ($q) => $q->orderBy('sort_order'), 'categories.items' => fn ($q) => $q->where('is_available', true), 'categories.items.variants' => fn ($q) => $q->where('is_available', true), 'categories.items.modifierGroups.options' => fn ($q) => $q->where('is_available', true)])->get()->map(fn ($menu) => ['id' => $menu->id, 'name' => $menu->name, 'categories' => $menu->categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name, 'items' => $category->items->map(fn ($item) => [...$item->only('id', 'public_id', 'name', 'description', 'price', 'tax_basis_points', 'image_path'), 'variants' => $item->variants->map(fn ($v) => $v->only('id', 'name', 'price')), 'modifier_groups' => $item->modifierGroups->map(fn ($g) => [...$g->only('id', 'name', 'min_choices', 'max_choices'), 'options' => $g->options->map(fn ($o) => $o->only('id', 'name', 'price'))])])])])->all()),
            'fulfillment' => $location->fulfillment, 'zones' => $location->zones()->where('is_active', true)->get(), 'hours' => $location->hours];
    }

    public function menu(Request $request, string $location)
    {
        return response()->json($this->data($this->resolve($request, $location)));
    }

    public function checkout(CheckoutRequest $request, string $location, PlaceOrderAction $action)
    {
        $result = $action->execute($this->resolve($request, $location), $request->validated(), (string) $request->header('Idempotency-Key'));

        return response()->json(['order_id' => $result['order']->public_id, 'tracking_token' => $result['tracking_token'], 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function tracking(Request $request, string $order)
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) === 64, 404);
        $model = Order::where('public_id', $order)->where('tracking_token_hash', hash('sha256', $token))->firstOrFail();
        $mapped = $request->attributes->get('storefront_location');
        abort_if($mapped && (int) $mapped !== $model->location_id, 404);

        return response()->json(['public_id' => $model->public_id, 'status' => $model->status->value, 'total' => $model->total, 'currency' => $model->currency, 'history' => $model->history()->get(['to_status', 'created_at']), 'restaurant' => $model->location->restaurant->only('name', 'slug', 'description', 'tagline', 'primary_color', 'logo_path', 'cover_path', 'contact_email', 'contact_phone', 'website')])->header('Cache-Control', 'no-store');
    }
}
