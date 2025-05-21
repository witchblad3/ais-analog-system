<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'source_site',
        'name',
        'price',
        'info',
        'file_name',
        'link'
    ];

    protected $casts = [
        'info' => 'array',
    ];

    public function characteristicValues()
    {
        return $this->hasMany(ProductCharacteristicValue::class);
    }
}


