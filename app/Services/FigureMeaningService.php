<?php

namespace App\Services;

use App\Models\FigureMeaning;
use App\Support\CardPackCatalog;
use Throwable;

class FigureMeaningService
{
    public function __construct(
        private readonly OpenAIClient $openAI,
        private readonly AppSettings $settings,
    ) {}

    public function text(string $pack, string $figureId, string $locale, string $figureName = ''): string
    {
        $slug = CardPackCatalog::canonicalize($pack);
        $figureId = trim($figureId);
        $locale = $locale === 'en' ? 'en' : 'hu';
        if ($slug === '' || $figureId === '') {
            return '';
        }

        $row = FigureMeaning::query()
            ->where('pack_slug', $slug)
            ->where('figure_id', $figureId)
            ->first();

        $stored = $row?->text($locale) ?? '';
        if ($stored !== '') {
            return $stored;
        }

        $fromDeck = CardPackCatalog::figureBlurb($slug, $figureId, $locale);
        if ($fromDeck !== '') {
            $this->store($slug, $figureId, $locale, $fromDeck, FigureMeaning::SOURCE_DECK);

            return $fromDeck;
        }

        $generated = $this->askLlm($slug, $figureId, $locale, $figureName);
        if ($generated !== '') {
            $this->store($slug, $figureId, $locale, $generated, FigureMeaning::SOURCE_LLM);
        }

        return $generated;
    }

    private function store(string $slug, string $figureId, string $locale, string $text, string $source): void
    {
        $column = $locale === 'en' ? 'meaning_en' : 'meaning_hu';
        $row = FigureMeaning::query()->firstOrNew([
            'pack_slug' => $slug,
            'figure_id' => $figureId,
        ]);
        $row->{$column} = $text;
        if ($row->source === null || $row->source === '') {
            $row->source = $source;
        } elseif ($source === FigureMeaning::SOURCE_LLM && $row->source === FigureMeaning::SOURCE_DECK) {
            $row->source = FigureMeaning::SOURCE_LLM;
        }
        $row->save();
    }

    private function askLlm(string $slug, string $figureId, string $locale, string $figureName): string
    {
        if (! $this->settings->llmEnabled()) {
            return '';
        }

        $name = $figureName !== '' ? $figureName : CardPackCatalog::figureName($slug, $figureId, $locale);
        $system = $locale === 'en'
            ? 'You write a short traditional card meaning. One or two sentences, no headings, no lists.'
            : 'Rövid, hagyományos kártyajelentést írsz. Egy-két mondat, címsor és lista nélkül.';
        $user = $locale === 'en'
            ? "Pack: {$slug}. Figure id: {$figureId}. Name: {$name}. Write the meaning."
            : "Pakli: {$slug}. Figura azonosító: {$figureId}. Név: {$name}. Írd meg a jelentését.";

        try {
            $result = $this->openAI->complete([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], $this->openAI->selectedModel(), [
                'temperature' => 0.5,
                'max_tokens' => 180,
            ]);
            $text = trim((string) data_get($result->response->json(), 'choices.0.message.content', ''));
        } catch (Throwable) {
            return '';
        }

        return $text;
    }
}
