<?php

namespace App\Http\Controllers;

use App\Domain\Rewards\RewardCampaigns;
use App\Domain\Rewards\RewardCards;
use App\Domain\Tenancy\TenantAccess;
use App\Models\RestaurantLocation;
use App\Models\StorefrontDomain;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RewardsController extends Controller
{
    public function __construct(private RewardCampaigns $campaigns, private RewardCards $cards) {}

    public function wallet(Request $request, string $location, PublicOrderingController $storefront)
    {
        $place = $storefront->resolve($request, $location);

        return Inertia::render('storefront/Rewards', [
            'locationId' => $place->public_id, 'locationName' => $place->name, 'restaurant' => $place->restaurant->name,
            'menuPath' => $request->attributes->has('storefront_location') ? '/' : $place->storefront_path,
            'campaigns' => $this->campaigns->summary($place),
        ])->withViewData(['privatePage' => true])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function customer(Request $request, string $location, string $action, PublicOrderingController $storefront)
    {
        $place = $storefront->resolve($request, $location);
        abort_unless($request->expectsJson() && $request->isJson(), 406);
        if ($action === 'create') {
            return $this->json(['token' => $this->cards->create($place)], 201);
        }
        $data = $request->validate([
            'token' => ['required', 'string', 'regex:/^[a-zA-Z0-9]{64}$/'],
            'code' => [$action === 'join' ? 'required' : 'nullable', 'string', 'regex:/^[a-zA-Z0-9]{12}$/'],
        ]);
        $card = $this->cards->resolve($place, $data['token']);
        if ($action === 'code') {
            return $this->json($this->cards->cardCode($place, $card));
        }
        $reference = $action === 'join' ? $this->cards->join($place, $card, $data['code']) : null;

        return $this->json(['campaigns' => $this->campaigns->summary($place, $card->id), 'reference' => $reference]);
    }

    public function index(Request $request, string $location)
    {
        $place = $this->staffLocation($request, $location);
        $props = app(WorkspaceController::class)->workspaceProps($request, $location, 'orders');

        $domain = StorefrontDomain::where('location_id', $place->id)->where('verification_status', 'verified')->orderBy('id')->value('hostname');
        $walletUrl = ($domain ? 'https://'.$domain : $request->getSchemeAndHttpHost()).'/rewards/'.$place->public_id;

        return Inertia::render('restaurant/Rewards', [
            'walletUrl' => $walletUrl,
            ...$props, 'campaigns' => $this->campaigns->summary($place, staff: true),
            'products' => $props['canManage'] ? $this->campaigns->products($place)->orderBy('name')->get(['public_id', 'name']) : [],
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, string $location, ?string $campaign = null)
    {
        $place = $this->staffLocation($request, $location);
        app(TenantAccess::class)->requireManage($request->user(), $place->organization_id);
        $this->campaigns->save($place, $request->all(), $campaign);

        return $this->json(['campaigns' => $this->campaigns->summary($place, staff: true)]);
    }

    public function counter(Request $request, string $location, string $action)
    {
        $place = $this->staffLocation($request, $location);
        $code = ['required', 'string', 'regex:/^[a-zA-Z0-9]{12}$/'];
        $campaign = ['required', 'ulid'];

        switch ($action) {
            case 'pending':
                return $this->json(['pending' => $this->cards->pending($place)]);
            case 'venue':
                $data = $request->validate(['campaign' => $campaign]);

                return $this->json($this->cards->venueCode($place, $data['campaign']));
            case 'inspect':
                $data = $request->validate(['code' => $code]);

                return $this->json(['campaigns' => $this->cards->inspect($place, $data['code'])]);
            case 'confirm':
                $data = $request->validate([
                    'code' => ['required_without:pending', 'prohibits:pending', 'nullable', 'string', 'regex:/^[a-zA-Z0-9]{12}$/'],
                    'pending' => ['required_without:code', 'nullable', 'integer', 'min:1'],
                    'campaign' => $campaign, 'quantity' => ['required', 'integer', 'between:1,100'],
                    'receipt' => ['required', 'string', 'max:80'],
                ]);
                $this->cards->confirm($place, $request->user(), $data);
                break;
            case 'redeem':
                $data = $request->validate(['code' => $code, 'campaign' => $campaign]);
                $this->cards->redeem($place, $request->user(), $data['code'], $data['campaign']);
                break;
        }

        return $this->json(['ok' => true]);
    }

    private function staffLocation(Request $request, string $location): RestaurantLocation
    {
        $place = RestaurantLocation::where('public_id', $location)->firstOrFail();
        app(TenantAccess::class)->requireFeature($request->user(), $place, 'orders', true);

        return $place;
    }

    private function json(array $data, int $status = 200)
    {
        return response()->json($data, $status)->header('Cache-Control', 'private, no-store');
    }
}
