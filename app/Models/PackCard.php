<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackCard extends Model
{
    protected $fillable = [
        'card_pack_id',
        'figure_id',
        'meaning_entry_id',
        'sort_order',
        'name_hu',
        'name_en',
        'keyword_hu',
        'keyword_en',
        'description_hu',
        'description_en',
        'kind',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(CardPack::class, 'card_pack_id');
    }

    public function meaningEntry(): BelongsTo
    {
        return $this->belongsTo(MeaningEntry::class);
    }

    public function imageUrl(): ?string
    {
        $path = trim((string) $this->image_path);
        if ($path === '' || ! is_file(public_path($path))) {
            return null;
        }

        return asset($path);
    }
}
