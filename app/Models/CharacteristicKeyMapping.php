<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharacteristicKeyMapping extends Model
{
    protected $fillable = [
        'site',
        'remote_key',
        'characteristic_id',
        'file_name',
    ];

    public function characteristic()
    {
        return $this->belongsTo(Characteristic::class);
    }
}


