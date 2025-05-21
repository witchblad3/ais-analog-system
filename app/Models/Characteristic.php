<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Characteristic extends Model
{
    protected $fillable = ['key'];

    public function mappings()
    {
        return $this->hasMany(CharacteristicKeyMapping::class);
    }
}


