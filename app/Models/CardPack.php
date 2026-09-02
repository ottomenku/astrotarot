<?php

namespace App\Models;

use Database\Factories\CardPackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardPack extends Model
{
    /** @use HasFactory<CardPackFactory> */
    use HasFactory;

    public const TYPE_TAROT = 'tarot';

    public const TYPE_ORACLE = 'oracle';

    public const TYPE_CARD_BACK = 'card_back';

    public const TYPE_BACKGROUND = 'background';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_TAROT,
        self::TYPE_ORACLE,
        self::TYPE_CARD_BACK,
        self::TYPE_BACKGROUND,
    ];

    /** @var list<string> */
    public const FIGURE_TYPES = [
        self::TYPE_TAROT,
        self::TYPE_ORACLE,
    ];

    public const POLARITY_LIGHT = 'light';

    public const POLARITY_SHADOW = 'shadow';

    /** @var list<string> */
    public const POLARITIES = [
        self::POLARITY_LIGHT,
        self::POLARITY_SHADOW,
    ];

    protected $fillable = [
        'slug',
        'name_hu',
        'name_en',
        'description_hu',
        'description_en',
        'type',
        'polarity',
        'card_count',
        'style',
        'owner',
        'preview_path',
        'asset_path',
        'is_active',
        'is_default',
        'sort_order',
        'default_card_back_id',
        'default_background_light_id',
        'default_background_shadow_id',
    ];

    protected function casts(): array
    {
        return [
            'card_count' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function defaultCardBack(): BelongsTo
    {
        return $this->belongsTo(self::class, 'default_card_back_id');
    }

    public function defaultBackgroundLight(): BelongsTo
    {
        return $this->belongsTo(self::class, 'default_background_light_id');
    }

    public function defaultBackgroundShadow(): BelongsTo
    {
        return $this->belongsTo(self::class, 'default_background_shadow_id');
    }

    public function gameRules(): HasMany
    {
        return $this->hasMany(GameRule::class, 'default_pack_id');
    }

    public function name(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'en' ? $this->name_en : $this->name_hu;
    }

    public function description(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'en' ? (string) $this->description_en : (string) $this->description_hu;
    }

    public function isFigure(): bool
    {
        return in_array($this->type, self::FIGURE_TYPES, true);
    }

    public function previewUrl(): ?string
    {
        $path = trim((string) $this->preview_path);
        if ($path !== '' && is_file(public_path($path))) {
            return asset($path);
        }

        if ($this->type === self::TYPE_CARD_BACK) {
            return $this->fileUrl();
        }

        if ($this->type === self::TYPE_BACKGROUND) {
            return $this->fileUrl();
        }

        return null;
    }

    public function fileUrl(): ?string
    {
        $relative = $this->fileRelative();

        return $relative !== null ? asset($relative) : null;
    }

    public function fileRelative(): ?string
    {
        $path = trim((string) $this->asset_path);
        if ($path === '') {
            return null;
        }
        if (is_file(public_path($path))) {
            return $path;
        }

        return null;
    }

    public function folderRelative(): ?string
    {
        $path = trim((string) $this->asset_path);
        if ($path === '') {
            return 'card-pack/'.$this->slug;
        }
        if (is_dir(public_path($path))) {
            return $path;
        }
        if (is_file(public_path($path))) {
            return dirname($path);
        }

        return 'card-pack/'.$this->slug;
    }

    public static function defaultFor(string $type, ?string $polarity = null): ?self
    {
        $query = static::query()->active()->ofType($type);
        if ($type === self::TYPE_BACKGROUND && $polarity !== null) {
            $query->where('polarity', $polarity);
        }

        return (clone $query)->where('is_default', true)->ordered()->first()
            ?? $query->ordered()->first();
    }

    public function makeDefault(): void
    {
        $query = static::query()->where('type', $this->type)->where('id', '!=', $this->id);
        if ($this->type === self::TYPE_BACKGROUND) {
            $query->where('polarity', $this->polarity);
        }
        $query->update(['is_default' => false]);
        $this->update(['is_default' => true, 'is_active' => true]);
    }

    /**
     * @param  Builder<CardPack>  $query
     * @return Builder<CardPack>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<CardPack>  $query
     * @return Builder<CardPack>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @param  Builder<CardPack>  $query
     * @return Builder<CardPack>
     */
    public function scopeFigure(Builder $query): Builder
    {
        return $query->whereIn('type', self::FIGURE_TYPES);
    }

    /**
     * @param  Builder<CardPack>  $query
     * @return Builder<CardPack>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name_hu');
    }
}
