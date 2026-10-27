<?php

namespace App\Domain\Imports;

use App\Models\ImportBatch;

class ImportVerification
{
    public function evidence(array $menu): array
    {
        $baskets = [];
        foreach ($menu['categories'] as $c) {
            foreach ($c['items'] as $i) {
                foreach ($i['variants'] ?: [null] as $v) {
                    $price = $v['price'] ?? $i['price'];
                    $choices = [];
                    foreach ([...$c['groups'], ...$i['groups'], ...($v['groups'] ?? [])] as $g) {
                        $options = array_values(array_filter($g['options'], fn ($o) => $o['is_default']));
                        if (! $options && $g['is_required']) {
                            $options = array_slice($g['options'], 0, max(1, $g['min_choices']));
                        }foreach ($options as $o) {
                            $price += $o['price'];
                            $choices[] = ['group' => $g['name'], 'option' => $o['name'], 'quantity' => 1, 'price' => $o['price']];
                        }
                    }$baskets[] = ['product_key' => $i['key'], 'product' => $i['name'], 'variant' => $v['name'] ?? null, 'unit_price' => $price, 'quantity' => 1, 'total' => $price, 'currency' => $menu['currency'], 'choices' => $choices];
                }
            }
        }

        return $baskets;
    }

    public function diff(ImportBatch $batch, array $menu): array
    {
        $previous = app(PublishImport::class)->previous($batch);
        $old = $previous ? app(ProcessImport::class)->canonical(ImportBatch::findOrFail($previous->batch_id)) : ['categories' => []];
        $flatten = function ($value) {
            $rows = [];
            foreach ($value['categories'] as $c) {
                foreach ($c['items'] as $i) {
                    $rows[$i['key']] = ['category' => $c['name'], 'category_groups' => $c['groups'], 'item' => $i];
                }
            }

            return $rows;
        };
        $before = $flatten($old);
        $after = $flatten($menu);
        $result = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $a = $before[$key] ?? null;
            $b = $after[$key] ?? null;
            if ($a === $b) {
                continue;
            }$result[] = ['key' => (string) $key, 'name' => ($b ?? $a)['item']['name'], 'change' => $a === null ? 'added' : ($b === null ? 'removed' : 'changed')];
        }

        return $result;
    }
}
