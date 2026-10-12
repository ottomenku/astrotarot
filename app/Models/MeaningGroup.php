<?php

namespace App\Models;

use Database\Factories\MeaningGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MeaningGroup extends Model
{
    /** @use HasFactory<MeaningGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name_hu',
        'name_en',
        'description_hu',
        'description_en',
        'type',
        'owner_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(MeaningEntry::class)->orderBy('sort_order')->orderBy('id');
    }

    public function packs(): HasMany
    {
        return $this->hasMany(CardPack::class);
    }

    public function name(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'en' && filled($this->name_en)
            ? (string) $this->name_en
            : (string) $this->name_hu;
    }

    public function description(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $text = $locale === 'en' && filled($this->description_en)
            ? (string) $this->description_en
            : (string) $this->description_hu;

        return (string) $text;
    }

    public function replicateWithEntries(?User $owner = null): self
    {
        $copy = $this->replicate();
        $copy->slug = self::uniqueSlug($this->slug);
        $copy->name_hu = $this->copyName((string) $this->name_hu, 'hu');
        $copy->name_en = $this->copyName((string) $this->name_en, 'en');
        $copy->owner_id = $owner?->id;
        $copy->save();

        foreach ($this->entries as $entry) {
            $clone = $entry->replicate();
            $clone->meaning_group_id = $copy->id;
            $clone->save();
        }

        return $copy->load('entries');
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'meaning-group';
        }
        $candidate = $slug;
        $i = 2;
        while (self::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    private function copyName(string $name, string $locale): string
    {
        $name = trim($name);
        $suffix = $locale === 'en' ? ' copy' : ' másolat';
        if ($name === '') {
            return $locale === 'en' ? 'Copy' : 'Másolat';
        }

        return $name.$suffix;
    }
}
