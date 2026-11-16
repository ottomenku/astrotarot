<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingLog extends Model
{
    protected $fillable = [
        'user_id',
        'session_public_id',
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

    public function readingSession(): BelongsTo
    {
        return $this->belongsTo(ReadingSession::class, 'session_public_id', 'public_id');
    }

    public function scopeAdminRoots($query)
    {
        return $query->where(function ($outer) {
            $outer->whereNull('session_public_id')
                ->orWhere('session_public_id', '')
                ->orWhereIn('id', function ($sub) {
                    $sub->from('reading_logs')
                        ->selectRaw('MIN(id)')
                        ->whereNotNull('session_public_id')
                        ->where('session_public_id', '!=', '')
                        ->groupBy('session_public_id');
                });
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, self>
     */
    public function thread()
    {
        if (! filled($this->session_public_id)) {
            return collect([$this]);
        }

        return static::query()
            ->where('session_public_id', $this->session_public_id)
            ->orderBy('id')
            ->get();
    }

    public function turnKind(): string
    {
        $mode = (string) data_get($this->astro, 'mode', 'initial');

        return in_array($mode, ['initial', 'clarify', 'expand', 'refine'], true) ? $mode : 'initial';
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
