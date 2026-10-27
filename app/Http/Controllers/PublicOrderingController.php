<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\RecordMenuVisit;
use App\Domain\Menus\PublicMenuTree;
use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Restaurants\StorefrontShareImage;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class PublicOrderingController extends Controller
{
    public function slug(Request $request, string $slug, ?string $location = null)
    {
        $restaurant = Restaurant::where('slug', $slug)->firstOrFail();
        $places = $restaurant->locations()->orderBy('id')->get();
        if ($mapped = $request->attributes->get('storefront_location')) {
            $places = $places->where('id', $mapped);
            abort_if($places->isEmpty(), 404);
        }
        if ($location) {
            $selected = $places->firstWhere('public_id', $location);
            abort_unless($selected, 404);

            return $this->page($request, $selected);
        }
        abort_if($places->isEmpty(), 404);
        if ($places->count() === 1) {
            return $this->page($request, $places->first());
        }

        return Inertia::render('storefront/Locations', ['restaurant' => $restaurant->only('name', 'slug', 'logo_path', 'primary_color'), 'locations' => $places->map(fn ($place) => $place->only('name', 'address', 'storefront_path'))->values()]);
    }

    public function page(Request $request, RestaurantLocation $location)
    {
        $restaurant = $location->restaurant;
        $title = $restaurant->name.' '.(app()->getLocale() === 'en' ? 'Digital Menu' : 'Meniu Digital');
        $image = app(StorefrontShareImage::class)->url($restaurant);
        $meta = ['title' => $title, 'name' => $restaurant->name, 'description' => __('Discover the menu and choose your favourite dish!'), 'image' => $image, 'url' => $request->url(), 'locale' => app()->getLocale() === 'en' ? 'en_GB' : 'ro_RO'];

        return Inertia::render('storefront/Menu', [...$this->data($location), 'shareMeta' => $meta])->withViewData(['storefrontMeta' => $meta]);
    }

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
            'menus' => Cache::remember('public-menu:'.$location->public_id, 30, fn () => app(PublicMenuTree::class)->build($location)),
            'fulfillment' => $location->fulfillment ?? ['pickup_enabled' => false, 'delivery_enabled' => false, 'reservations_enabled' => false], 'zones' => $location->zones()->where('is_active', true)->get(), 'hours' => $location->hours];
    }

    public function menu(Request $request, string $location)
    {
        return response()->json($this->data($this->resolve($request, $location)));
    }

    public function visit(Request $request, string $location)
    {
        app(RecordMenuVisit::class)->execute($request, $this->resolve($request, $location));

        return response()->noContent()->header('Cache-Control', 'no-store');
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
