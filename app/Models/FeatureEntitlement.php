<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureEntitlement extends Model
{
    protected $fillable = [
        'plan_key',
        'feature_key',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
