<?php

namespace App\Domain\Imports;

use App\Jobs\DownloadGloriaFoodPhotos;
use App\Models\ImportBatch;
use App\Models\RestaurantLocation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ProcessImport
{
    public function execute(ImportBatch $batch, array $source): void
    {
        $batch->refresh();
        $adapter = app(GloriaFoodAdapter::class);
        $identity = $adapter->identity($source);
        $resolution = app(ResolveImportSource::class)->execute($batch, $identity);
        if ($batch->status === 'queued' && empty($batch->settings['nutrition_map'])) {
            $previous = $resolution['previous'];
            if (! empty($previous?->settings['nutrition_map'])) {
                $batch->update(['settings' => [...$batch->settings, 'nutrition_map' => $previous->settings['nutrition_map']]]);
            }
        }
        $normalized = $adapter->normalize($source, $batch->settings);
        $normalized['menu']['source_restaurant'] = $resolution['namespace'];
        $normalized['menu']['resolved_restaurant_id'] = $resolution['restaurant_id'];
        if ($resolution['conflict']) {
            $normalized['issues'][] = ['severity' => 'blocker', 'code' => 'source_identity_conflict', 'path' => 'restaurant_id', 'message' => 'Multiple source identities share this menu ID. Confirm the source restaurant before publication.'];
        }
        $evidence = app(ImportVerification::class)->evidence($normalized['menu']);
        $enriched = app(EnrichGloriaFoodImport::class)->execute($batch, $normalized['menu']);
        $normalized['menu'] = $enriched['menu'];
        if (isset($normalized['menu']['public_menu'])) {
            $normalized['issues'] = array_values(array_filter($normalized['issues'], fn ($issue) => $issue['code'] !== 'photos'));
        }
        $normalized['issues'] = [...$normalized['issues'], ...$enriched['issues']];
        DB::transaction(function () use ($batch, $source, $normalized, $evidence) {
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
            $report = ['adapter_version' => GloriaFoodAdapter::VERSION, 'source_restaurant' => $menu['source_restaurant'], 'source_menu' => $menu['source_menu'], 'reported_restaurant_id' => $menu['reported_restaurant_id'], 'resolved_restaurant_id' => $menu['resolved_restaurant_id'], 'currency' => $menu['currency'], 'settings' => $batch->settings, 'products' => array_sum(array_map(fn ($c) => count($c['items']), $menu['categories'])), 'categories' => count($menu['categories']), 'blockers' => count(array_filter($issues, fn ($i) => $i['severity'] === 'blocker')), 'source_changes' => app(ImportVerification::class)->diff($batch, $menu), 'synthetic_baskets' => $evidence, 'checked_at' => now()->toIso8601String(), 'checksum' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR))];
            $report['public_menu'] = $menu['public_menu'] ?? null;
            DB::table('import_reports')->insert(['batch_id' => $batch->id, 'report' => json_encode($report, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            $enriching = ($menu['public_menu']['pending'] ?? 0) > 0;
            $batch->update(['source_restaurant' => $menu['source_restaurant'], 'status' => $enriching ? 'enriching' : 'ready', 'credential' => null]);
            if ($enriching) {
                DB::afterCommit(fn () => DownloadGloriaFoodPhotos::enqueue($batch->id));
            }
        });
    }

    public function canonical(ImportBatch $batch): array
    {
        return json_decode(Crypt::decryptString(DB::table('import_snapshots')->where('batch_id', $batch->id)->value('canonical')), true, 64, JSON_THROW_ON_ERROR);
    }
}
