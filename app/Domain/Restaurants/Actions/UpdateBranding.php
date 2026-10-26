<?php

namespace App\Domain\Restaurants\Actions;

use App\Domain\Restaurants\StorefrontShareImage;
use App\Domain\Tenancy\TenantAccess;
use App\Models\Restaurant;
use App\Models\StorefrontDomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UpdateBranding
{
    public function execute(User $actor, Restaurant $restaurant, array $data): Restaurant
    {
        app(TenantAccess::class)->requireManage($actor, $restaurant->organization_id);
        if (in_array($data['slug'], config('zionfood.reserved_slugs'), true) || Restaurant::where('slug', $data['slug'])->where('id', '!=', $restaurant->id)->exists()) {
            throw ValidationException::withMessages(['slug' => __('This storefront address is unavailable.')]);
        }
        $newHostname = $data['slug'].'.'.config('zionfood.storefront_base_domain');
        $locationIds = $restaurant->locations()->pluck('id');
        if (StorefrontDomain::where('hostname', $newHostname)->whereNotIn('location_id', $locationIds)->exists()) {
            throw ValidationException::withMessages(['slug' => __('This storefront address is unavailable.')]);
        }
        $uploaded = [];
        $old = [];
        try {
            foreach (['logo', 'cover'] as $kind) {
                if (! empty($data['remove_'.$kind])) {
                    $data[$kind.'_path'] = null;
                    $old[] = $restaurant->{$kind.'_path'};
                }
                if (isset($data[$kind])) {
                    $path = $data[$kind]->store('branding/'.$restaurant->organization_id.'/'.$restaurant->public_id, 'public');
                    $uploaded[] = $path;
                    $data[$kind.'_path'] = $path;
                    $old[] = $restaurant->{$kind.'_path'};
                }
            }

            return DB::transaction(function () use ($restaurant, $data, $newHostname, $locationIds, $old) {
                $record = Restaurant::whereKey($restaurant->id)->lockForUpdate()->firstOrFail();
                $oldHostname = $record->slug.'.'.config('zionfood.storefront_base_domain');
                $record->update(array_intersect_key($data, array_flip(['name', 'slug', 'description', 'tagline', 'primary_color', 'contact_email', 'contact_phone', 'website', 'logo_path', 'cover_path'])));
                StorefrontDomain::whereIn('location_id', $locationIds)->where('type', 'subdomain')->where('hostname', $oldHostname)->update(['hostname' => $newHostname]);
                DB::afterCommit(function () use ($old, $record) {
                    foreach (array_unique(array_filter($old)) as $path) {
                        Storage::disk('public')->delete(app(StorefrontShareImage::class)->path($record, $path));
                        if (str_starts_with($path, 'branding/'.$record->organization_id.'/'.$record->public_id.'/')) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                });

                return $record->fresh();
            }, 3);
        } catch (\Throwable $e) {
            foreach ($uploaded as $path) {
                Storage::disk('public')->delete($path);
            } throw $e;
        }
    }
}
