<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    public const LIGHT_SHADOW_FULL_ASTRO = 'full-astro';

    public const LIGHT_SHADOW_ORACLE_ASTRO = 'oracle-astro';

    public const LIGHT_SHADOW_FULL_RANDOM = 'full-random';

    /** @var list<string> */
    public const LIGHT_SHADOW_MODES = [
        self::LIGHT_SHADOW_FULL_ASTRO,
        self::LIGHT_SHADOW_ORACLE_ASTRO,
        self::LIGHT_SHADOW_FULL_RANDOM,
    ];

    public const ASTRO_DATA_NONE = 'none';

    public const ASTRO_DATA_NOW = 'now';

    public const ASTRO_DATA_PERSONAL = 'personal';

    public const ASTRO_DATA_RELATIONS = 'relations';

    public const ASTRO_DATA_ASK = 'ask';

    /** @var list<string> */
    public const ASTRO_DATA_MODES = [
        self::ASTRO_DATA_NONE,
        self::ASTRO_DATA_NOW,
        self::ASTRO_DATA_PERSONAL,
        self::ASTRO_DATA_RELATIONS,
        self::ASTRO_DATA_ASK,
    ];

    public const SPREAD_YES_NO = 'YES_NO';

    public const SPREAD_TIMING = 'TIMING';

    public const SPREAD_RELATIONSHIP = 'RELATIONSHIP';

    public const SPREAD_DILEMMA = 'DILEMMA';

    public const SPREAD_GENERAL = 'GENERAL';

    /** @var list<string> */
    public const SPREAD_TYPES = [
        self::SPREAD_YES_NO,
        self::SPREAD_TIMING,
        self::SPREAD_RELATIONSHIP,
        self::SPREAD_DILEMMA,
        self::SPREAD_GENERAL,
    ];

    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name_hu',
        'name_en',
        'description_hu',
        'description_en',
        'icon',
        'has_question',
        'prompt_hu',
        'prompt_en',
        'light_shadow_mode',
        'astro_data_mode',
        'spread_types',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'has_question' => 'boolean',
            'spread_types' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(GameRule::class);
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

    public function prompt(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $text = $locale === 'en' ? (string) $this->prompt_en : (string) $this->prompt_hu;

        return trim($text);
    }

    public function iconUrl(): ?string
    {
        $path = trim((string) $this->icon);
        if ($path === '' || ! is_file(public_path($path))) {
            return null;
        }

        return asset($path);
    }

    /**
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id');
    }

    public function requestsCelestialLightShadow(?string $packType): bool
    {
        $mode = (string) ($this->light_shadow_mode ?: self::LIGHT_SHADOW_FULL_RANDOM);

        if ($mode === self::LIGHT_SHADOW_FULL_ASTRO) {
            return true;
        }

        if ($mode === self::LIGHT_SHADOW_ORACLE_ASTRO) {
            return $packType === CardPack::TYPE_ORACLE;
        }

        return false;
    }

    public function makeDefault(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true, 'is_active' => true]);
    }
}
