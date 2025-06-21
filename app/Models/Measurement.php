<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    protected $fillable = [
        'name',
        'description',
        'area',
        'category',
        'tags',
        'parcel_number',
        'perimeter',
        'coordinates_summary',
        'created_by',
        'last_modified_by',
        'is_shared',
        'access_level',
        'estimated_value',
        'zoning',
        'soil_data',
        'slope',
        'geojson',
        'user_id',
    ];
    
    protected $casts = [
        'geojson' => 'array',
        'coordinates_summary' => 'array',
        'soil_data' => 'array',
        'area' => 'decimal:2',
        'perimeter' => 'decimal:2',
        'estimated_value' => 'decimal:2',
        'slope' => 'decimal:2',
        'is_shared' => 'boolean',
    ];
    //
}
