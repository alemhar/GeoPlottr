<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    protected $fillable = ['geojson', 'user_id'];
    protected $casts = [
        'geojson' => 'array',
    ];
    //
}
