<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'role',
        'credits',
        'plan',
        'last_daily_credit_at',
        'plan_started_at',
        'template_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'credits' => 'integer',
            'last_daily_credit_at' => 'datetime',
            'plan_started_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function planKey(): string
    {
        return $this->plan ?: 'free';
    }

    public function isPremiumOrAbove(): bool
    {
        return in_array($this->planKey(), ['premium', 'expert'], true);
    }
}
