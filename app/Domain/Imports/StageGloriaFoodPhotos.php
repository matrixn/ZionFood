<?php

namespace App\Domain\Imports;

use App\Models\ImportBatch;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StageGloriaFoodPhotos
{
    public function execute(int $batchId, bool $stop = false): bool
    {
        // One bounded chunk per job. The batch lock serializes duplicate deliveries.
        return DB::transaction(function () use ($batchId, $stop) {
            $batch = ImportBatch::whereKey($batchId)->lockForUpdate()->first();
            if (! $batch || $batch->status !== 'enriching') {
                return false;
            }
            $menu = app(ProcessImport::class)->canonical($batch);
            $urls = [];
            foreach ($menu['categories'] as $category) {
                foreach ($category['items'] as $item) {
                    if (isset($item['photo_source_url'])) {
                        $urls[$item['key']] = $item['photo_source_url'];
                    }
                }
            }
            $meta = &$menu['public_menu'];
            $limited = ($meta['bytes'] ?? 0) >= 134217728;
            $chunk = $stop || $limited ? $urls : array_slice($urls, 0, 5, true);
            $responses = [];
            if (! $stop && ! $limited) {
                try {
                    $responses = app(GloriaFoodPublicClient::class)->photos($chunk);
                } catch (\Throwable) {
                    // This chunk fails explicitly; there is no unbounded retry.
                }
            }
            $photos = [];
            foreach ($chunk as $key => $url) {
                if ($stop || $limited) {
                    $meta[$limited ? 'limited' : 'failed']++;

                    continue;
                }
                try {
                    $response = $responses[$key] ?? null;
                    if ($response instanceof Response) {
                        $meta['bytes'] += strlen($response->body());
                    }
                    if ($meta['bytes'] > 134217728) {
                        $meta['limited']++;

                        continue;
                    }
                    $bytes = app(GloriaFoodPublicClient::class)->imageBytes($response);
                    $photos[$key] = app(ImportPhotos::class)->stage($batch, (string) $key, $bytes, $url);
                    $meta['downloaded']++;
                } catch (\Throwable) {
                    $meta['failed']++;
                }
            }
            foreach ($menu['categories'] as &$category) {
                foreach ($category['items'] as &$item) {
                    if (isset($item['photo_source_url']) && array_key_exists($item['key'], $chunk)) {
                        unset($item['photo_source_url']);
                    }
                    if (isset($photos[$item['key']])) {
                        $item['photo'] = $photos[$item['key']];
                    }
                }
                unset($item);
            }
            unset($category);
            $pending = count($urls) - count($chunk);
            $meta['pending'] = $pending;
            if (! $pending) {
                $meta['status'] = 'verified';
                if ($meta['failed'] || $meta['limited']) {
                    $issue = ['severity' => 'warning', 'code' => 'public_photos_incomplete', 'path' => 'public_menu_url', 'message' => 'public_photos_incomplete'];
                    $meta['issues'][] = $issue;
                    DB::table('import_issues')->insert(['batch_id' => $batch->id, ...$issue, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            DB::table('import_snapshots')->where('batch_id', $batch->id)->update(['canonical' => Crypt::encryptString(json_encode($menu, JSON_THROW_ON_ERROR)), 'updated_at' => now()]);
            $report = json_decode(DB::table('import_reports')->where('batch_id', $batch->id)->value('report'), true);
            $report['public_menu'] = $meta;
            if (! $pending) {
                $report['source_changes'] = app(ImportVerification::class)->diff($batch, $menu);
                $batch->update(['status' => 'ready']);
                Log::info('GloriaFood photos staged', ['batch_id' => $batch->public_id, 'downloaded' => $meta['downloaded'], 'missing' => $meta['missing'], 'failed' => $meta['failed'], 'limited' => $meta['limited']]);
            } else {
                $batch->touch();
            }
            DB::table('import_reports')->where('batch_id', $batch->id)->update(['report' => json_encode($report, JSON_THROW_ON_ERROR), 'updated_at' => now()]);

            return $pending > 0;
        }, 3);
    }
}
