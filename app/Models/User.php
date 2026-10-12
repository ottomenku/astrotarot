<?php

namespace App\Models;

use App\Enums\Role;
use App\Services\FeatureAccessService;
use App\Support\FeatureCatalog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
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
        'last_monthly_credit_at',
        'plan_started_at',
        'template_id',
        'terms_accepted_at',
        'privacy_accepted_at',
        'banned_at',
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
            'last_monthly_credit_at' => 'datetime',
            'plan_started_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'banned_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function readingLogs(): HasMany
    {
        return $this->hasMany(ReadingLog::class);
    }

    public function readingShares(): HasMany
    {
        return $this->hasMany(ReadingShare::class);
    }

    public function creditRequests(): HasMany
    {
        return $this->hasMany(CreditRequest::class);
    }

    public function dataDeletionRequests(): HasMany
    {
        return $this->hasMany(DataDeletionRequest::class);
    }

    public function packPreferences(): HasMany
    {
        return $this->hasMany(UserPackPreference::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function planKey(): string
    {
        return $this->plan ?: 'free';
    }

    public function usesPasswordLogin(): bool
    {
        if (in_array($this->provider, ['google', 'facebook'], true)) {
            return false;
        }

        return filled($this->password);
    }

    public function isPremiumOrAbove(): bool
    {
        $features = app(FeatureAccessService::class);

        return $features->can($this, FeatureCatalog::HISTORY)
            || $features->can($this, FeatureCatalog::PDF);
    }
}
