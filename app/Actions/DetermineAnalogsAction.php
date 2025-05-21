<?php

namespace App\Actions;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DetermineAnalogsAction
{
    /**
     * Возвращает коллекцию аналогов (Product), у которых совпадает
     * не менее 50% уникальных параметров (по «мягкому» ключу и точному значению).
     */
    public function execute(Product $base): Collection
    {
        $baseId = $base->id;

        // 1) Сборка параметров базового товара
        $baseRows = DB::table('product_characteristic_values as pcv')
            ->leftJoin('characteristic_key_mappings as ckm', 'pcv.characteristic_key_mapping_id', '=', 'ckm.id')
            ->leftJoin('characteristics as ch',        'pcv.characteristic_id',              '=', 'ch.id')
            ->where('pcv.product_id', $baseId)
            ->selectRaw('COALESCE(ckm.remote_key, ch.key) as param, pcv.value')
            ->get();

        if ($baseRows->isEmpty()) {
            return collect();
        }

        // Объединяем дубликаты по ключу, берём последнее значение
        $baseParams = [];
        foreach ($baseRows as $r) {
            $baseParams[$r->param] = $r->value;
        }
        $totalParams = count($baseParams);
        $minMatches  = (int) ceil($totalParams * 0.5);

        // 2) Сборка всех остальных параметров
        $otherRows = DB::table('product_characteristic_values as pcv')
            ->leftJoin('characteristic_key_mappings as ckm', 'pcv.characteristic_key_mapping_id', '=', 'ckm.id')
            ->leftJoin('characteristics as ch',        'pcv.characteristic_id',              '=', 'ch.id')
            ->where('pcv.product_id', '<>', $baseId)
            ->select('pcv.product_id', DB::raw('COALESCE(ckm.remote_key, ch.key) as param'), 'pcv.value')
            ->get();

        // Группируем по product_id
        $others = [];
        foreach ($otherRows as $r) {
            $others[$r->product_id][$r->param] = $r->value;
        }

        // 3) Для каждого товара считаем совпадения
        $matchCounts = [];
        foreach ($others as $pid => $params) {
            $matches = 0;
            foreach ($baseParams as $bKey => $bVal) {
                // 3a) точное совпадение ключа
                if (isset($params[$bKey]) && $params[$bKey] === $bVal) {
                    $matches++;
                    continue;
                }
                // 3b) «мягкое» совпадение ключей по Levenshtein ≤20%
                foreach ($params as $oKey => $oVal) {
                    $len = max(mb_strlen($bKey), mb_strlen($oKey));
                    if ($len > 0
                        && levenshtein(mb_strtolower($bKey), mb_strtolower($oKey)) / $len <= 0.2
                        && $oVal === $bVal
                    ) {
                        $matches++;
                        break;
                    }
                }
            }
            if ($matches >= $minMatches) {
                $matchCounts[$pid] = $matches;
            }
        }

        if (empty($matchCounts)) {
            return collect();
        }

        // 4) Получаем модели и присваиваем match_count
        arsort($matchCounts);
        $ids      = array_keys($matchCounts);
        $products = Product::whereIn('id', $ids)->get()->keyBy('id');

        return collect($matchCounts)
            ->map(function($cnt, $pid) use($products) {
                $p = $products->get($pid);
                if ($p) {
                    $p->match_count = $cnt;
                    return $p;
                }
            })
            ->filter();
    }

    /**
     * Возвращает процент совпадения.
     */
    public function matchPercent(Product $base, Product $analog): int
    {
        $matched = $analog->match_count ?? 0;

        $total = DB::table('product_characteristic_values as pcv')
            ->selectRaw('COUNT(DISTINCT COALESCE(pcv.characteristic_key_mapping_id, pcv.characteristic_id)) as cnt')
            ->where('pcv.product_id', $base->id)
            ->value('cnt');

        if ($total === 0) {
            return 0;
        }
        return (int) round($matched / $total * 100);
    }
}
