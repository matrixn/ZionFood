<?php

namespace App\Domain\Imports;

use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

class ResolveImportSource
{
    public function execute(ImportBatch $batch, array $identity): array
    {
        $reported = $identity['reported_restaurant_id'];
        $history = DB::table('import_batches as b')->join('import_reports as r', 'r.batch_id', '=', 'b.id')
            ->where('b.organization_id', $batch->organization_id)->where('b.location_id', $batch->location_id)
            ->where('b.status', 'published')->where('r.report->source_menu', $identity['source_menu'])
            ->orderByDesc('b.id')->get(['b.id', 'b.source_restaurant', 'r.report->resolved_restaurant_id as resolved_restaurant_id'])
            ->map(function ($row) {
                // MariaDB can return JSON null as the literal string "null".
                $resolved = (string) ($row->resolved_restaurant_id ?? '');
                $row->restaurant = ctype_digit($resolved) ? $resolved : (ctype_digit($row->source_restaurant) ? $row->source_restaurant : null);

                return $row;
            });
        $known = $history->pluck('restaurant')->filter(fn ($value) => $value !== null)->unique()->values();
        $conflict = false;
        $previous = null;
        if ($reported !== null) {
            $previous = $history->first(fn ($row) => $row->restaurant === $reported);
            if (! $previous && $known->isEmpty()) {
                $unknown = $history->whereNull('restaurant');
                $conflict = $unknown->pluck('source_restaurant')->unique()->count() > 1;
                $previous = $conflict ? null : $unknown->first();
            }
        } else {
            $conflict = $known->count() > 1 || ($known->isEmpty() && $history->pluck('source_restaurant')->unique()->count() > 1);
            $previous = $conflict ? null : ($known->isNotEmpty() ? $history->first(fn ($row) => $row->restaurant === $known->first()) : $history->first());
        }

        return ['namespace' => $previous?->source_restaurant ?? ($reported ?? 'menu:'.$identity['source_menu']), 'restaurant_id' => $reported ?? $previous?->restaurant, 'previous' => $previous ? ImportBatch::find($previous->id) : null, 'conflict' => $conflict];
    }
}
