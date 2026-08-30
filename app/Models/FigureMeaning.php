<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FigureMeaning extends Model
{
    public const SOURCE_DECK = 'deck';

    public const SOURCE_LLM = 'llm';

    public const SOURCE_ADMIN = 'admin';

    protected $fillable = [
        'pack_slug',
        'figure_id',
        'meaning_hu',
        'meaning_en',
        'source',
    ];

    public function text(string $locale): string
    {
        $locale = $locale === 'en' ? 'en' : 'hu';
        $primary = $locale === 'en' ? (string) $this->meaning_en : (string) $this->meaning_hu;
        $fallback = $locale === 'en' ? (string) $this->meaning_hu : (string) $this->meaning_en;

        return trim($primary) !== '' ? trim($primary) : trim($fallback);
    }
}
