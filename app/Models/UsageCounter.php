<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageCounter extends Model
{
    protected $fillable = [
        'tenant_id',
        'resource_key',
        'current_value',
        'reset_at',
    ];

    protected $casts = [
        'reset_at' => 'datetime',
    ];
}
