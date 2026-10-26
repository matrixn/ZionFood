<?php

namespace App\Domain\Restaurants\Actions;

use App\Models\Cuisine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManageCuisine
{
    public function execute(User $actor, ?Cuisine $context, array $data): Cuisine
    {
        abort_unless($actor->is_platform_admin, 403);
        $upload = isset($data['image']) ? $data['image']->store('cuisine-catalog', 'public') : null;
        try {
            return DB::transaction(function () use ($context, $data, $upload) {
                $record = $context ? Cuisine::whereKey($context->id)->lockForUpdate()->firstOrFail() : new Cuisine;
                $old = $record->image_path;
                $record->fill(array_intersect_key($data, array_flip(['slug', 'name_ro', 'name_en', 'description_ro', 'description_en', 'is_active', 'sort_order', 'image_credit', 'image_source_url', 'image_license', 'image_license_url'])));
                if ($upload) {
                    $record->image_path = '/storage/'.$upload;
                }
                $record->save();
                if ($upload && $old && str_starts_with($old, '/storage/cuisine-catalog/')) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete(substr($old, strlen('/storage/'))));
                }

                return $record;
            }, 3);
        } catch (\Throwable $e) {
            if ($upload) {
                Storage::disk('public')->delete($upload);
            }
            throw $e;
        }
    }
}
