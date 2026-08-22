<?php

namespace App\Services;

use Illuminate\Http\Client\Response;

final class LlmChatResult
{
    public function __construct(
        public readonly Response $response,
        public readonly string $provider,
        public readonly string $model,
        public readonly bool $fallback = false,
    ) {
    }

    public function label(string $locale = 'hu'): string
    {
        $name = $this->provider === 'ollama' ? 'Ollama' : 'OpenAI';
        $label = trim($name.' · '.$this->model, ' ·');

        if ($this->fallback) {
            $label .= $locale === 'en' ? ' (fallback)' : ' (tartalék)';
        }

        return $label;
    }

    /**
     * @return array{provider: string, model: string, fallback: bool, label: string}
     */
    public function toArray(string $locale = 'hu'): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'fallback' => $this->fallback,
            'label' => $this->label($locale),
        ];
    }
}
