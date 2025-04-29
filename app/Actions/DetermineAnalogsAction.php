<?php

namespace App\Actions;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class DetermineAnalogsAction
{
    public function execute(Product $product): Collection
    {
        $parameters = $product->parameters;

        $firstThreeParameters = array_slice($parameters, 0, 3);

        $analogs = Product::query()
            ->where('manufacturer', $product->manufacturer)
            ->where('id', '!=', $product->id)
            ->where(function ($query) use ($firstThreeParameters) {
                foreach ($firstThreeParameters as $param) {
                    $query->whereJsonContains('parameters', $param);
                }
            })
            ->get();

        return $analogs;
    }
}


