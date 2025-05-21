<?php

namespace App\Http\Controllers;

use App\Actions\DetermineAnalogsAction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Показывает страницу с формой поиска и пустой таблицей результатов.
     */
    public function appView()
    {
        return view('app');
    }

    /**
     * AJAX‑эндпоинт: возвращает до 10 вариантов автодополнения по полю term.
     */
    public function suggestions(Request $request)
    {
        $q = trim($request->get('term', ''));
        // например, вернём до 50 вариантов
        $limit = (int) $request->get('limit', 20);

        $names = Product::query()
            ->where('name', 'like', "%{$q}%")
            ->distinct('name')
            ->limit($limit)
            ->pluck('name');

        return response()->json($names);
    }

    /**
     * AJAX‑эндпоинт: получает GET-параметр name и возвращает JSON
     * с базовым товаром и списком аналогов (>=50% совпадений).
     */
    public function apiAnalogs(Request $request, DetermineAnalogsAction $action)
    {
        set_time_limit(300);
        $request->validate([
            'name'       => 'required|string',
            'length'     => 'integer|min:1|max:100',
            'start'      => 'integer|min:0',
            'sort_by'    => 'in:source_site,name,price,match_percent',
            'sort_dir'   => 'in:asc,desc',
        ]);

        $name    = $request->name;
        $start   = (int) $request->get('start', 0);
        $length  = (int) $request->get('length', 10);
        $sortBy  = $request->get('sort_by', 'match_percent');
        $sortDir = $request->get('sort_dir', 'desc');

        $base = Product::where('name', $name)->firstOrFail();

        $allAnalogs = $action->execute($base);
        $analogIds  = $allAnalogs->pluck('id')->all();

        $idsForParams = array_merge([$base->id], $analogIds);

        $rawParams = DB::table('product_characteristic_values as pcv')
            ->leftJoin('characteristic_key_mappings as ckm', 'pcv.characteristic_key_mapping_id', '=', 'ckm.id')
            ->leftJoin('characteristics as ch',        'pcv.characteristic_id',              '=', 'ch.id')
            ->whereIn('pcv.product_id', $idsForParams)
            ->select([
                'pcv.product_id',
                DB::raw('COALESCE(ckm.remote_key, ch.key) as param_name'),
                'pcv.value',
            ])
            ->get();

        $paramsByProduct = $rawParams
            ->groupBy('product_id')
            ->map(fn($group) => $group->pluck('value','param_name')->all())
            ->toArray();

        $baseBlock = [
            'source_site' => $base->source_site,
            'name'        => $base->name,
            'external_id' => $base->external_id,
            'price'       => $base->price,
            'parameters'  => $paramsByProduct[$base->id] ?? [],
        ];

        $rows = $allAnalogs->map(function($p) use($action, $base, $paramsByProduct){
            return [
                'source_site'   => $p->source_site,
                'name'          => $p->name,
                'external_id'   => $p->external_id,
                'price'         => $p->price,
                'match_percent' => $action->matchPercent($base, $p),
                'parameters'    => $paramsByProduct[$p->id] ?? [],
            ];
        });

        $rows = collect($rows)
            ->sortBy(fn($item) => is_string($item[$sortBy])
                ? mb_strtolower($item[$sortBy])
                : $item[$sortBy],
                SORT_REGULAR,
                $sortDir === 'desc')
            ->values();

        $total = $rows->count();
        $paged = $rows->slice($start, $length)->values();

        return response()->json([
            'base'            => $baseBlock,
            'analogs'         => $paged,
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
        ]);
    }

}
