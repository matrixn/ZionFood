<?php

namespace App\Domain\Imports;

use App\Domain\Tenancy\TenantAccess;
use App\Models\ImportBatch;
use App\Models\RestaurantLocation;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportRestaurantSettings
{
    private const ADDRESS = ['address', 'city', 'postal_code', 'country', 'latitude', 'longitude'];

    public function state(RestaurantLocation $location): array
    {
        $l = $location->fresh(['restaurant', 'hours', 'zones']);
        $basics = $l->setup_profile['basics'] ?? [];
        $profiles = [];
        foreach ($l->restaurant->locations()->orderBy('id')->get() as $sibling) {
            $profiles[$sibling->id] = array_intersect_key($sibling->setup_profile['basics'] ?? [], ['restaurant_name' => true]);
        }
        $address = ['address' => $l->address];
        foreach (array_slice(self::ADDRESS, 1) as $key) {
            $address[$key] = $basics[$key] ?? null;
            if (in_array($key, ['latitude', 'longitude'], true) && $address[$key] !== null) {
                $address[$key] = (float) $address[$key];
            }
        }

        return [
            'logo' => ['value' => $l->restaurant->logo_path],
            'name' => ['value' => $l->restaurant->name, 'profiles' => $profiles],
            'phone' => ['value' => $l->restaurant->contact_phone, 'profile' => array_intersect_key($basics, ['phone' => true])],
            'address' => ['value' => $address, 'profile' => array_intersect_key($basics, array_flip(self::ADDRESS))],
            'hours' => ['value' => ['timezone' => $l->timezone, 'hours' => $l->hours->sortBy('weekday')->map(fn ($r) => ['weekday' => (int) $r->weekday, 'opens_at' => substr($r->opens_at, 0, 5), 'closes_at' => substr($r->closes_at, 0, 5)])->values()->all()]],
            'zones' => ['value' => $l->zones->sortBy('id')->map(fn ($z) => ['id' => $z->id, 'name' => $z->name, 'postal_prefix' => $z->postal_prefix ?? '', 'fee' => (int) $z->fee, 'minimum_order' => (int) $z->minimum_order, 'geometry' => $z->geometry, 'priority' => (int) $z->priority, 'is_active' => (bool) $z->is_active])->values()->all()],
        ];
    }

    public function preview(ImportBatch $batch, array $menu): array
    {
        $candidates = $menu['public_menu']['settings_candidates'] ?? [];
        if (! $candidates) {
            return [];
        }
        $location = RestaurantLocation::findOrFail($batch->location_id);
        $state = $this->state($location);
        $siblings = $location->restaurant->locations()->orderBy('id')->pluck('name')->all();
        $fields = [];
        foreach (NormalizePublicRestaurantSettings::FIELDS as $key) {
            if (! isset($candidates[$key])) {
                continue; // Older staged imports retain their original choices.
            }
            $candidate = $candidates[$key];
            $after = $candidate['value'];
            $added = 0;
            if ($key === 'zones' && $candidate['available']) {
                $plan = $this->zonePlan($batch, $state['zones']['value'], $after);
                $after = [...$state['zones']['value'], ...$plan['added']];
                $added = count($plan['added']);
                if (count($after) > 30) {
                    $candidate['available'] = false;
                    $candidate['error'] = 'zones_limit';
                }
            }
            $fields[] = ['key' => $key, 'available' => $candidate['available'], 'error' => $candidate['error'], 'current' => $state[$key]['value'], 'proposed' => $after,
                'current_setup_phone' => $key === 'phone' ? ($state['phone']['profile']['phone'] ?? null) : null, 'changed' => $key === 'zones' ? $added > 0 : $state[$key]['value'] !== $after, 'added_zones' => $added,
                'current_image_url' => $key === 'logo' && $state['logo']['value'] ? Storage::disk('public')->url($state['logo']['value']) : null,
                'proposed_image_url' => $key === 'logo' && $candidate['available'] ? '/app/locations/'.$location->public_id.'/migration-center/'.$batch->public_id.'/logo' : null,
                'affected_locations' => in_array($key, ['name', 'phone', 'logo'], true) ? $siblings : [$location->name]];
        }

        return $fields;
    }

    private function zonePlan(ImportBatch $batch, array $existing, array $source): array
    {
        $added = $links = [];
        foreach ($source as $zone) {
            $mapped = DB::table('import_entity_mappings as m')->join('import_batches as b', 'b.id', '=', 'm.batch_id')
                ->where('b.organization_id', $batch->organization_id)->where('m.location_id', $batch->location_id)
                ->where('b.status', 'published')->where('m.namespace', 'gloriafood:'.$batch->source_restaurant)
                ->where('m.entity_type', 'delivery_zone')->where('m.external_id', $zone['source_id'])->orderByDesc('m.id')->value('m.local_id');
            $match = collect($existing)->first(fn ($row) => ($mapped && $row['id'] === (int) $mapped) || $this->zoneKey($row) === $this->zoneKey($zone));
            if (! $match) {
                $match = collect($added)->first(fn ($row) => $this->zoneKey($row) === $this->zoneKey($zone));
            }
            $links[$zone['source_id']] = $match && isset($match['id']) ? ['local_id' => $match['id']] : ['source_id' => $match['source_id'] ?? $zone['source_id']];
            if (! $match) {
                $added[] = $zone;
            }
        }

        return ['added' => $added, 'links' => $links];
    }

    private function zoneKey(array $zone): string
    {
        $values = [];
        foreach (['name', 'postal_prefix', 'fee', 'minimum_order', 'geometry', 'is_active'] as $key) {
            $values[$key] = $zone[$key];
        }

        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public function apply(User $actor, RestaurantLocation $location, ImportBatch $batch, array $menu, array $fields, array &$createdPhotos = []): ?string
    {
        if (! $fields) {
            return null;
        }
        app(TenantAccess::class)->requireManage($actor, $location->organization_id);
        app(TenantAccess::class)->requireFeature($actor, $location, 'menu', true);
        abort_unless($batch->organization_id === $location->organization_id && $batch->location_id === $location->id, 409);
        if (count($fields) !== count(array_unique($fields)) || array_diff($fields, NormalizePublicRestaurantSettings::FIELDS)) {
            throw ValidationException::withMessages(['settings_fields' => __('Choose only available restaurant settings from the preview.')]);
        }
        $preview = collect($this->preview($batch, $menu))->keyBy('key');
        foreach ($fields as $field) {
            if (! ($preview[$field]['available'] ?? false)) {
                throw ValidationException::withMessages(['settings_fields' => __('Choose only available restaurant settings from the preview.')]);
            }
            if (in_array($field, ['hours', 'zones'], true)) {
                app(TenantAccess::class)->requireFeature($actor, $location, 'orders', true);
            }
        }
        $before = array_intersect_key($this->state($location), array_flip($fields));
        $addedIds = [];
        foreach ($fields as $field) {
            $value = $menu['public_menu']['settings_candidates'][$field]['value'];
            if ($field === 'logo') {
                $value = app(ImportPhotos::class)->publish($batch, 'logo', $menu['public_menu']['logo'], $createdPhotos);
                $this->write($location, $field, $value);
            } elseif ($field === 'zones') {
                $plan = $this->zonePlan($batch, $before['zones']['value'], $value);
                $newIds = [];
                $priority = min(1000, (int) $location->zones()->max('priority') + 1);
                foreach ($plan['added'] as $zone) {
                    $row = $location->zones()->create([...array_diff_key($zone, ['source_id' => true]), 'priority' => $priority]);
                    $newIds[$zone['source_id']] = $row->id;
                    $addedIds[] = $row->id;
                }
                foreach ($plan['links'] as $sourceId => $link) {
                    $localId = $link['local_id'] ?? $newIds[$link['source_id']];
                    DB::table('import_entity_mappings')->insert(['batch_id' => $batch->id, 'location_id' => $location->id, 'namespace' => 'gloriafood:'.$batch->source_restaurant, 'entity_type' => 'delivery_zone', 'external_id' => (string) $sourceId, 'local_id' => $localId]);
                }
            } else {
                $this->write($location, $field, $value);
            }
        }
        $after = array_intersect_key($this->state($location), array_flip($fields));
        $report = json_decode(DB::table('import_reports')->where('batch_id', $batch->id)->value('report'), true);
        $report['settings_changes'] = [];
        foreach ($fields as $field) {
            $report['settings_changes'][$field] = ['before' => $before[$field]['value'], 'after' => $after[$field]['value']];
        }
        DB::table('import_reports')->where('batch_id', $batch->id)->update(['report' => json_encode($report, JSON_THROW_ON_ERROR), 'updated_at' => now()]);

        return Crypt::encryptString(json_encode(['organization_id' => $location->organization_id, 'location_id' => $location->id, 'restaurant_id' => $location->restaurant_id, 'before' => $before, 'after' => $after, 'added_zone_ids' => $addedIds], JSON_THROW_ON_ERROR));
    }

    private function basics(RestaurantLocation $location, array $keys, array $values): void
    {
        $location->refresh();
        $profile = $location->setup_profile ?? [];
        $profile['basics'] = [...array_diff_key($profile['basics'] ?? [], array_flip($keys)), ...$values];
        $location->update(['setup_profile' => $profile, 'setup_revision' => $location->setup_revision + 1]);
    }

    private function write(RestaurantLocation $location, string $field, mixed $value, ?array $restore = null): void
    {
        if ($field === 'logo') {
            // Retain the previous file for guarded rollback and existing share previews.
            $location->restaurant()->update(['logo_path' => $value]);
        } elseif ($field === 'name') {
            $location->restaurant()->update(['name' => $value]);
            foreach ($location->restaurant->locations()->orderBy('id')->get() as $sibling) {
                $this->basics($sibling, ['restaurant_name'], $restore === null ? ['restaurant_name' => $value] : $restore['profiles'][$sibling->id]);
            }
        } elseif ($field === 'phone') {
            $location->restaurant()->update(['contact_phone' => $value]);
            $this->basics($location, ['phone'], $restore['profile'] ?? ['phone' => $value]);
        } elseif ($field === 'address') {
            $location->update(['address' => $value['address']]);
            $this->basics($location, self::ADDRESS, $restore['profile'] ?? $value);
        } elseif ($field === 'hours') {
            $location->update(['timezone' => $value['timezone']]);
            $location->hours()->delete();
            foreach ($value['hours'] as $row) {
                $location->hours()->create($row);
            }
            $location->increment('setup_revision');
        }
    }

    public function revert(User $actor, RestaurantLocation $location, ?string $snapshot): void
    {
        if (! $snapshot) {
            return;
        }
        $change = json_decode(Crypt::decryptString($snapshot), true, 64, JSON_THROW_ON_ERROR);
        app(TenantAccess::class)->requireManage($actor, $location->organization_id);
        app(TenantAccess::class)->requireFeature($actor, $location, 'menu', true);
        abort_unless($change['organization_id'] === $location->organization_id && $change['location_id'] === $location->id && $change['restaurant_id'] === $location->restaurant_id, 409);
        $current = array_intersect_key($this->state($location), $change['after']);
        abort_unless($this->hash($current) === $this->hash($change['after']), 409, __('The imported settings changed. Automatic rollback would discard local edits.'));
        foreach ($change['before'] as $field => $before) {
            if (in_array($field, ['hours', 'zones'], true)) {
                app(TenantAccess::class)->requireFeature($actor, $location, 'orders', true);
            }
            if ($field === 'zones') {
                $location->zones()->whereIn('id', $change['added_zone_ids'])->delete();
            } else {
                $this->write($location, $field, $before['value'], $before);
            }
        }
    }

    public function hash(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }
}
