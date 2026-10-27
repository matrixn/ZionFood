<?php

namespace App\Domain\Imports;

use App\Models\ImportBatch;
use App\Models\RestaurantLocation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ProcessImport
{
    public function execute(ImportBatch $batch, array $source): void
    {
        $batch->refresh();
        if ($batch->status === 'queued' && empty($batch->settings['nutrition_map']) && isset($source['restaurant_id']) && is_int($source['restaurant_id'])) {
            $previous = ImportBatch::where('location_id', $batch->location_id)->where('organization_id', $batch->organization_id)->where('source_restaurant', (string) $source['restaurant_id'])->where('status', 'published')->latest('id')->first();
            if (! empty($previous?->settings['nutrition_map'])) {
                $batch->update(['settings' => [...$batch->settings, 'nutrition_map' => $previous->settings['nutrition_map']]]);
            }
        }
        $normalized = app(GloriaFoodAdapter::class)->normalize($source, $batch->settings);
        DB::transaction(function () use ($batch, $source, $normalized) {
            $batch = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if (! in_array($batch->status, ['queued', 'ready'], true)) {
                return;
            }
            DB::table('import_snapshots')->where('batch_id', $batch->id)->delete();
            DB::table('import_reports')->where('batch_id', $batch->id)->delete();
            DB::table('import_issues')->where('batch_id', $batch->id)->delete();
            $menu = $normalized['menu'];
            $location = RestaurantLocation::findOrFail($batch->location_id);
            $issues = $normalized['issues'];
            if ($menu['currency'] !== $location->currency) {
                $issues[] = ['severity' => 'blocker', 'code' => 'currency', 'path' => 'menu.currency', 'message' => 'Source currency differs from location currency. No automatic conversion is performed.'];
            }
            DB::table('import_snapshots')->insert(['batch_id' => $batch->id, 'source' => Crypt::encryptString(json_encode($source, JSON_THROW_ON_ERROR)), 'canonical' => Crypt::encryptString(json_encode($menu, JSON_THROW_ON_ERROR)), 'checksum' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($issues as $issue) {
                DB::table('import_issues')->insert(['batch_id' => $batch->id, ...$issue, 'created_at' => now(), 'updated_at' => now()]);
            }
            $batch->source_restaurant = $menu['source_restaurant'];
            $report = ['adapter_version' => GloriaFoodAdapter::VERSION, 'source_restaurant' => $menu['source_restaurant'], 'source_menu' => $menu['source_menu'], 'currency' => $menu['currency'], 'settings' => $batch->settings, 'products' => array_sum(array_map(fn ($c) => count($c['items']), $menu['categories'])), 'categories' => count($menu['categories']), 'blockers' => count(array_filter($issues, fn ($i) => $i['severity'] === 'blocker')), 'source_changes' => app(ImportVerification::class)->diff($batch, $menu), 'synthetic_baskets' => app(ImportVerification::class)->evidence($menu), 'checked_at' => now()->toIso8601String(), 'checksum' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR))];
            DB::table('import_reports')->insert(['batch_id' => $batch->id, 'report' => json_encode($report, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            $batch->update(['source_restaurant' => $menu['source_restaurant'], 'status' => 'ready', 'credential' => null]);
        });
    }

    public function canonical(ImportBatch $batch): array
    {
        return json_decode(Crypt::decryptString(DB::table('import_snapshots')->where('batch_id', $batch->id)->value('canonical')), true, 64, JSON_THROW_ON_ERROR);
    }
}
