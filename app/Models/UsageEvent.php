<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'resource_key',
        'quantity',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'created_at' => 'datetime',
    ];
}
