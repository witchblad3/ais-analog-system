<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCharacteristicValue extends Model
{
    protected $fillable = [
        'product_id',
        'characteristic_id',
        'characteristic_key_mapping_id',
        'value',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(Characteristic::class);
    }
}


