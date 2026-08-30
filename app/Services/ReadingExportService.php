<?php

namespace App\Services;

use App\Models\ReadingShare;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReadingExportService
{
    public function __construct(private readonly OgImageService $ogImages) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{locale: string, question: string, cards: list<array<string, mixed>>, answers: list<array{mode: string, label: string, text: string}>}
     */
    public function normalizePayload(array $data): array
    {
        $locale = (($data['locale'] ?? 'hu') === 'en') ? 'en' : 'hu';
        $question = trim((string) ($data['question'] ?? ''));
        $cards = array_values(is_array($data['cards'] ?? null) ? $data['cards'] : []);
        $answersIn = array_values(is_array($data['answers'] ?? null) ? $data['answers'] : []);

        if ($question === '' || $cards === [] || $answersIn === []) {
            throw ValidationException::withMessages([
                'payload' => $locale === 'en'
                    ? 'A complete reading is required for export.'
                    : 'A teljes olvasat szükséges az exporthoz.',
            ]);
        }

        $answers = [];
        foreach ($answersIn as $answer) {
            if (! is_array($answer)) {
                continue;
            }
            $text = trim((string) ($answer['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $mode = (string) ($answer['mode'] ?? 'initial');
            $label = trim((string) ($answer['label'] ?? ''));
            if ($label === '') {
                $label = $this->defaultAnswerLabel($locale, $mode);
            }
            $answers[] = [
                'mode' => $mode,
                'label' => $label,
                'text' => $text,
                'source' => trim((string) ($answer['source'] ?? '')),
            ];
        }

        if ($answers === []) {
            throw ValidationException::withMessages([
                'answers' => $locale === 'en'
                    ? 'At least one answer is required.'
                    : 'Legalább egy válasz szükséges.',
            ]);
        }

        $normalizedCards = [];
        foreach ($cards as $index => $card) {
            if (! is_array($card)) {
                continue;
            }
            $normalizedCards[] = [
                'slot_title' => (string) ($card['slot_title'] ?? ($index === 0
                    ? ($locale === 'en' ? 'Celestial Master' : 'Égi mester')
                    : ($locale === 'en' ? 'Chosen Figure' : 'Választott figura'))),
                'figure_id' => (string) ($card['figure_id'] ?? ''),
                'figure_name' => (string) ($card['figure_name'] ?? $card['figure_id'] ?? ''),
                'keyword' => (string) ($card['keyword'] ?? ''),
                'blurb' => (string) ($card['blurb'] ?? ''),
                'polarity' => (string) ($card['polarity'] ?? $card['variant'] ?? 'positive'),
                'variant' => (string) ($card['variant'] ?? 'positive'),
                'deck' => (string) ($card['deck'] ?? ($index === 0 ? 'oracle' : 'tarot')),
                'astro_caption' => (string) ($card['astro_caption'] ?? ''),
                'image_src' => $this->canEmbedImages()
                    ? $this->cardImageSrc(
                        (string) ($card['deck'] ?? ($index === 0 ? 'oracle' : 'tarot')),
                        (string) ($card['figure_id'] ?? '')
                    )
                    : null,
                'image_url' => $this->ogImages->figurePublicUrl(
                    (string) ($card['deck'] ?? ($index === 0 ? 'oracle' : 'tarot')),
                    (string) ($card['figure_id'] ?? '')
                ),
            ];
        }

        if ($normalizedCards === []) {
            throw ValidationException::withMessages([
                'cards' => $locale === 'en'
                    ? 'At least one card is required.'
                    : 'Legalább egy lap szükséges.',
            ]);
        }

        return [
            'locale' => $locale,
            'question' => $question,
            'cards' => $normalizedCards,
            'answers' => $answers,
        ];
    }

    /**
     * @param  array{locale: string, question: string, cards: list<array<string, mixed>>, answers: list<array{mode: string, label: string, text: string}>}  $payload
     */
    public function renderHtml(array $payload, bool $forPdf = false): string
    {
        return View::make('exports.reading', [
            'payload' => $payload,
            'forPdf' => $forPdf,
            'generatedAt' => now(),
        ])->render();
    }

    /**
     * @param  array{locale: string, question: string, cards: list<array<string, mixed>>, answers: list<array{mode: string, label: string, text: string}>}  $payload
     */
    public function makePdf(array $payload): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->renderHtml($payload, true), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  array{locale: string, question: string, cards: list<array<string, mixed>>, answers: list<array{mode: string, label: string, text: string}>}  $payload
     */
    public function createShare(?User $user, array $payload): ReadingShare
    {
        return ReadingShare::query()->create([
            'user_id' => $user?->id,
            'token' => Str::random(40),
            'payload' => $payload,
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function defaultAnswerLabel(string $locale, string $mode): string
    {
        return match ($mode) {
            'clarify' => $locale === 'en' ? 'Clarification' : 'Pontosítás',
            'expand' => $locale === 'en' ? 'Deeper reading' : 'Bővebb kifejtés',
            default => $locale === 'en' ? 'Reading' : 'Olvasat',
        };
    }

    private function canEmbedImages(): bool
    {
        return $this->ogImages->canGenerate();
    }

    private function cardImageSrc(string $deck, string $figureId): ?string
    {
        $absolute = $this->ogImages->figureAbsolutePath($deck, $figureId);
        if ($absolute === null) {
            return null;
        }

        $binary = @file_get_contents($absolute);
        if ($binary === false || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($binary);
    }
}
