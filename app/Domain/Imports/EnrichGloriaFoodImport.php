<?php

namespace App\Domain\Imports;

use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnrichGloriaFoodImport
{
    public function execute(ImportBatch $batch, array $menu): array
    {
        if (empty($batch->settings['public_menu_url'])) {
            return ['menu' => $menu, 'issues' => []];
        }
        // Revalidation uses the same staged evidence, without further external requests.
        if (DB::table('import_snapshots')->where('batch_id', $batch->id)->exists()) {
            $previous = app(ProcessImport::class)->canonical($batch);
            if (isset($previous['public_menu'])) {
                $photos = [];
                foreach ($previous['categories'] as $category) {
                    foreach ($category['items'] as $item) {
                        $photos[$item['key']] = $item['photo'] ?? null;
                    }
                }
                foreach ($menu['categories'] as &$category) {
                    foreach ($category['items'] as &$item) {
                        if ($photos[$item['key']] ?? null) {
                            $item['photo'] = $photos[$item['key']];
                        }
                    }
                    unset($item);
                }
                unset($category);
                $menu['public_menu'] = $previous['public_menu'];

                return ['menu' => $menu, 'issues' => $previous['public_menu']['issues']];
            }
        }
        $issues = [];
        $menu['public_menu'] = ['url' => $batch->settings['public_menu_url'], 'status' => 'unavailable', 'matched' => 0, 'downloaded' => 0, 'missing' => 0, 'failed' => 0, 'limited' => 0];
        $client = app(GloriaFoodPublicClient::class);
        try {
            $public = $client->menu($batch->settings['public_menu_url']);
            if ((string) ($public['menu']['id'] ?? '') !== $menu['source_menu'] || strtoupper($public['menu']['currency'] ?? '') !== $menu['currency'] || ($menu['resolved_restaurant_id'] !== null && (string) ($public['id'] ?? '') !== $menu['resolved_restaurant_id'])) {
                $menu['public_menu']['status'] = 'mismatch';
                $issues[] = $this->issue('blocker', 'public_menu_mismatch');
            } else {
                $menu['public_menu']['status'] = 'verified';
                $menu['public_menu']['restaurant'] = array_intersect_key($public, array_flip(['id', 'uid', 'name', 'address', 'phone', 'latitude', 'longitude', 'cuisines']));
                $menu['public_menu']['settings_candidates'] = app(NormalizePublicRestaurantSettings::class)->execute($public);
                $this->plan($menu, $public);
                $logo = &$menu['public_menu']['settings_candidates']['logo'];
                if ($logo['available']) {
                    $menu['public_menu']['logo_source_url'] = $logo['value']['source_url'];
                    $menu['public_menu']['pending']++;
                    $menu['public_menu']['status'] = 'pending';
                    $logo = ['available' => false, 'value' => null, 'error' => 'logo_pending'];
                }
                unset($logo);
                if ($menu['public_menu']['failed'] || $menu['public_menu']['limited']) {
                    $issues[] = $this->issue('warning', 'public_photos_incomplete');
                }
                if ($menu['public_menu']['missing']) {
                    $issues[] = $this->issue('warning', 'public_photos_missing');
                }
            }
        } catch (\Throwable $e) {
            $menu['public_menu']['status'] = 'unavailable';
            $issues[] = $this->issue('warning', 'public_menu_unavailable');
            Log::warning('GloriaFood public enrichment unavailable', ['batch_id' => $batch->public_id, 'exception_class' => $e::class]);
        }
        $menu['public_menu']['issues'] = $issues;

        return ['menu' => $menu, 'issues' => $issues];
    }

    private function plan(array &$menu, array $public): void
    {
        $publicItems = [];
        foreach ($public['menu']['categories'] ?? [] as $category) {
            foreach ($category['items'] ?? [] as $item) {
                $id = (string) ($item['id'] ?? '');
                // Duplicate IDs are ambiguous and must never select a photo.
                $publicItems[$id] = array_key_exists($id, $publicItems) ? null : $item;
            }
        }
        $urls = [];
        foreach ($menu['categories'] as $category) {
            foreach ($category['items'] as $item) {
                $id = $item['key'];
                $source = $publicItems[$id] ?? null;
                $picture = $public['pictures']['menu_item-'.$id] ?? null;
                $publicProduct = array_key_exists($id, $publicItems);
                if (($publicProduct && (! $source || trim($source['name'] ?? '') !== trim($item['name']))) || ! $picture || ($picture['thumbnail_type'] ?? '') !== 'menu_item' || (string) ($picture['entity_id'] ?? '') !== $id || ! preg_match('/^[a-f0-9-]{36}\.(jpg|jpeg|png|webp)$/D', $picture['filename'] ?? '')) {
                    $menu['public_menu']['missing']++;

                    continue;
                }
                $density = in_array($picture['density'] ?? 1, [2, 3], true) ? '_d'.$picture['density'] : '';
                $urls[$id] = GloriaFoodPublicClient::CDN.pathinfo($picture['filename'], PATHINFO_FILENAME).$density.'.'.pathinfo($picture['filename'], PATHINFO_EXTENSION);
            }
        }
        $menu['public_menu']['matched'] = count($urls);
        $menu['public_menu']['pending'] = count($urls);
        $menu['public_menu']['bytes'] = 0;
        if ($urls) {
            $menu['public_menu']['status'] = 'pending';
        }
        foreach ($menu['categories'] as &$category) {
            foreach ($category['items'] as &$item) {
                if (isset($urls[$item['key']])) {
                    $item['photo_source_url'] = $urls[$item['key']];
                }
            }
            unset($item);
        }
        unset($category);
    }

    private function issue(string $severity, string $code): array
    {
        return ['severity' => $severity, 'code' => $code, 'path' => 'public_menu_url', 'message' => $code];
    }
}
