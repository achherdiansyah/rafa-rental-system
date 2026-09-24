<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone_number',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isUser(): bool
    {
        return $this->role === UserRole::USER;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::OWNER;
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $roleValue = $role instanceof UserRole ? $role->value : $role;
            if ($this->role?->value === $roleValue) {
                return true;
            }
        }

        return false;
    }

    public function customerProfile()
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function projectLocations()
    {
        return $this->hasMany(ProjectLocation::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function recommendationRequests()
    {
        return $this->hasMany(RecommendationRequest::class);
    }
}
