<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'keycloak_subject',
        'email',
        'display_name',
        'status',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'user_profile_id');
    }

    public function adminUser(): HasOne
    {
        return $this->hasOne(AdminUser::class, 'user_profile_id');
    }

    public function isAdmin(): bool
    {
        return $this->adminUser()->where('status', 'active')->exists();
    }
}
