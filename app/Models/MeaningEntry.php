<?php

namespace App\Models;

use Database\Factories\MeaningEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeaningEntry extends Model
{
    /** @use HasFactory<MeaningEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'meaning_group_id',
        'figure_id',
        'sort_order',
        'name_hu',
        'name_en',
        'meaning_hu',
        'meaning_en',
        'description_hu',
        'description_en',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MeaningGroup::class, 'meaning_group_id');
    }

    public function name(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'en' && filled($this->name_en)
            ? (string) $this->name_en
            : (string) $this->name_hu;
    }

    public function meaning(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $primary = $locale === 'en' ? (string) $this->meaning_en : (string) $this->meaning_hu;
        $fallback = $locale === 'en' ? (string) $this->meaning_hu : (string) $this->meaning_en;
        $primary = trim($primary);

        return $primary !== '' ? $primary : trim($fallback);
    }

    public function description(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $primary = $locale === 'en' ? (string) $this->description_en : (string) $this->description_hu;
        $fallback = $locale === 'en' ? (string) $this->description_hu : (string) $this->description_en;
        $primary = trim($primary);

        return $primary !== '' ? $primary : trim($fallback);
    }
}
