<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use App\Support\PackageIcons;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_YEARLY = 'yearly';

    /** @var list<string> */
    public const PERIODS = [
        self::PERIOD_MONTHLY,
        self::PERIOD_YEARLY,
    ];

    protected $fillable = [
        'slug',
        'icon',
        'name_hu',
        'name_en',
        'price_huf',
        'price_usd',
        'period',
        'credits',
        'body_hu',
        'body_en',
        'perk_keys',
        'is_active',
        'sort_order',
        'can_export_pdf',
        'can_view_history',
        'has_astromotto',
    ];

    protected function casts(): array
    {
        return [
            'price_huf' => 'integer',
            'price_usd' => 'decimal:2',
            'credits' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'can_export_pdf' => 'boolean',
            'can_view_history' => 'boolean',
            'has_astromotto' => 'boolean',
            'perk_keys' => 'array',
        ];
    }

    public function iconKey(): string
    {
        return PackageIcons::resolve((string) $this->icon, (string) $this->slug);
    }

    public function iconSvg(): string
    {
        return PackageIcons::svg((string) $this->icon, (string) $this->slug);
    }

    public function featureAccess(): BelongsToMany
    {
        return $this->belongsToMany(FeatureAccess::class, 'feature_access_package');
    }

    public function isFree(): bool
    {
        return $this->slug === 'free'
            || ((int) $this->price_huf <= 0 && (float) $this->price_usd <= 0);
    }

    public function displayAmount(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        if ($locale === 'en') {
            $usd = round((float) $this->price_usd, 2);
            $decimals = abs($usd - round($usd)) < 0.001 ? 0 : 2;

            return '$'.number_format($usd, $decimals, '.', ',');
        }

        return number_format((int) $this->price_huf, 0, ',', ' ').' Ft';
    }

    public function name(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'en' ? $this->name_en : $this->name_hu;
    }

    public function body(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $html = $locale === 'en' ? (string) $this->body_en : (string) $this->body_hu;

        return HtmlSanitizer::planBody($html);
    }

    public function periodLabel(): string
    {
        if ($this->period === self::PERIOD_YEARLY) {
            return __('messages.plan_per_year');
        }

        return __('messages.plan_per_month');
    }

    /**
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
