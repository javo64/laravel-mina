<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'branch', 'profile', 'is_active', 'permissions', 'last_access_at'];
    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'last_access_at' => 'datetime',
        ];
    }

    public function canAccess(string $module): bool
    {
        return in_array($module, $this->permissions ?? [], true);
    }

    public function dailyReports()
    {
        return $this->hasMany(DailyReport::class);
    }
}
