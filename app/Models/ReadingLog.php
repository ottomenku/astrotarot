<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingLog extends Model
{
    protected $fillable = [
        'user_id',
        'locale',
        'question',
        'cards',
        'astro',
        'system_prompt',
        'user_prompt',
        'response',
        'model',
        'llm_provider',
        'llm_fallback',
        'status',
        'error',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'cards' => 'array',
            'astro' => 'array',
            'llm_fallback' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceLabel(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $provider = (string) ($this->llm_provider ?? '');
        $model = (string) ($this->model ?? '');
        $name = match ($provider) {
            'ollama' => 'Ollama',
            'openai' => 'OpenAI',
            default => '',
        };
        $label = trim($name.' · '.$model, ' ·');
        if ($label === '') {
            return '';
        }

        if ($this->llm_fallback) {
            $label .= $locale === 'en' ? ' (fallback)' : ' (tartalék)';
        }

        return $label;
    }
}
