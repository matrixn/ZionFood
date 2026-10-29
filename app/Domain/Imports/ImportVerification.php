<?php

namespace App\Domain\Imports;

use App\Models\ImportBatch;

class ImportVerification
{
    public const MAX_BASKETS = 10000;

    public const MAX_CHOICES = 50000;

    public const MAX_EVIDENCE_BYTES = 8388608;

    public function evidence(array $menu): array
    {
        $this->assertBudget($menu);
        $baskets = [];
        foreach ($menu['categories'] as $c) {
            foreach ($c['items'] as $i) {
                foreach ($i['variants'] ?: [null] as $v) {
                    $price = $v['price'] ?? $i['price'];
                    $choices = [];
                    foreach ([...$c['groups'], ...$i['groups'], ...($v['groups'] ?? [])] as $g) {
                        foreach ($this->selectedOptions($g) as $o) {
                            $price += $o['price'];
                            $choices[] = ['group' => $g['name'], 'option' => $o['name'], 'quantity' => 1, 'price' => $o['price']];
                        }
                    }$baskets[] = ['product_key' => $i['key'], 'product' => $i['name'], 'variant' => $v['name'] ?? null, 'unit_price' => $price, 'quantity' => 1, 'total' => $price, 'currency' => $menu['currency'], 'choices' => $choices];
                }
            }
        }

        return $baskets;
    }

    private function selectedOptions(array $group): array
    {
        $options = array_values(array_filter($group['options'], fn ($option) => $option['is_default']));

        return ! $options && $group['is_required'] ? array_slice($group['options'], 0, max(1, $group['min_choices'])) : $options;
    }

    private function selectionCost(array $groups): array
    {
        $count = $bytes = 0;
        foreach ($groups as $group) {
            foreach ($this->selectedOptions($group) as $option) {
                $count++;
                $bytes += 1 + strlen(json_encode(['group' => $group['name'], 'option' => $option['name'], 'quantity' => 1, 'price' => $option['price']], JSON_THROW_ON_ERROR));
            }
        }

        return [$count, $bytes];
    }

    private function assertBudget(array $menu): void
    {
        $totals = [0, 0, 0];
        foreach ($menu['categories'] as $category) {
            foreach ($category['items'] as $item) {
                $this->includeItemInBudget($category['groups'], $item, $menu['currency'], $totals);
            }
        }
    }

    /** Incremental preflight also stops normalization as soon as the aggregate budget is exceeded. */
    public function includeItemInBudget(array $categoryGroups, array $item, string $currency, array &$totals): void
    {
        [$baskets, $choices, $bytes] = $totals;
        [$categoryCount, $categoryBytes] = $this->selectionCost($categoryGroups);
        [$itemCount, $itemBytes] = $this->selectionCost($item['groups']);
        foreach ($item['variants'] ?: [null] as $variant) {
            [$variantCount, $variantBytes] = $this->selectionCost($variant['groups'] ?? []);
            $baskets++;
            $choices += $categoryCount + $itemCount + $variantCount;
            // Includes escaped UTF-8 names and conservative overhead for all numeric/structural fields.
            $bytes += 256 + strlen(json_encode([$item['key'], $item['name'], $variant['name'] ?? null, $currency], JSON_THROW_ON_ERROR)) + $categoryBytes + $itemBytes + $variantBytes;
            if ($baskets > self::MAX_BASKETS || $choices > self::MAX_CHOICES || $bytes > self::MAX_EVIDENCE_BYTES) {
                throw new ImportComplexityException('import_complexity_limit');
            }
        }
        $totals = [$baskets, $choices, $bytes];
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
