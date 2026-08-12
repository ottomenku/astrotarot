<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class AppSettings
{
    public const MIN_QUESTION_CHARS = 'min_question_chars';

    public const SYSTEM_PROMPT_HU = 'system_prompt_hu';

    public const SYSTEM_PROMPT_EN = 'system_prompt_en';

    public const DRAW_MODE = 'draw_mode';

    public const DRAW_MODE_SCORING = 'scoring';

    public const DRAW_MODE_RANDOM_POLARITY = 'random_polarity';

    public const SITE_ENABLED = 'site_enabled';

    public const LLM_ENABLED = 'llm_enabled';

    public const SLOT_ALLOCATION = 'slot_allocation';

    public const SLOT_SOURCE_SUN = 'sun_sign';

    public const SLOT_SOURCE_ELEMENT = 'element';

    public const SLOT_SOURCE_MODALITY = 'modality';

    public const SLOT_SOURCE_RANDOM1 = 'random1';

    public const SLOT_SOURCE_FULL_RANDOM = 'full_random';

    /** @var list<string> */
    public const SLOT_SOURCES = [
        self::SLOT_SOURCE_SUN,
        self::SLOT_SOURCE_ELEMENT,
        self::SLOT_SOURCE_MODALITY,
        self::SLOT_SOURCE_RANDOM1,
        self::SLOT_SOURCE_FULL_RANDOM,
    ];

    private const CACHE_KEY = 'app.settings.bag';

    public function minQuestionChars(): int
    {
        $value = (int) $this->get(self::MIN_QUESTION_CHARS, '10');

        return max(1, min(280, $value));
    }

    public function drawMode(): string
    {
        $mode = (string) $this->get(self::DRAW_MODE, self::DRAW_MODE_SCORING);

        return in_array($mode, [
            self::DRAW_MODE_SCORING,
            self::DRAW_MODE_RANDOM_POLARITY,
        ], true) ? $mode : self::DRAW_MODE_SCORING;
    }

    public function siteEnabled(): bool
    {
        return $this->flag(self::SITE_ENABLED, true);
    }

    public function llmEnabled(): bool
    {
        return $this->flag(self::LLM_ENABLED, true);
    }

    private function flag(string $key, bool $default): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return list<string>
     */
    public function slotAllocation(): array
    {
        $decoded = json_decode((string) $this->get(self::SLOT_ALLOCATION, ''), true);
        $defaults = [
            self::SLOT_SOURCE_SUN,
            self::SLOT_SOURCE_ELEMENT,
            self::SLOT_SOURCE_MODALITY,
        ];

        if (! is_array($decoded) || count($decoded) !== 3) {
            return $defaults;
        }

        $allocation = [];
        foreach (array_values($decoded) as $source) {
            $source = (string) $source;
            if (! in_array($source, self::SLOT_SOURCES, true)) {
                return $defaults;
            }
            $allocation[] = $source;
        }

        if (! $this->slotAllocationIsValid($allocation)) {
            return $defaults;
        }

        return $allocation;
    }

    /**
     * @param  list<string>  $allocation
     */
    public function slotAllocationIsValid(array $allocation): bool
    {
        if (count($allocation) !== 3) {
            return false;
        }

        foreach ($allocation as $source) {
            if (! in_array($source, self::SLOT_SOURCES, true)) {
                return false;
            }
        }

        return true;
    }

    public function systemPrompt(string $locale): string
    {
        $key = $locale === 'en' ? self::SYSTEM_PROMPT_EN : self::SYSTEM_PROMPT_HU;
        $fallback = $locale === 'en' ? $this->defaultSystemPromptEn() : $this->defaultSystemPromptHu();

        $value = trim((string) $this->get($key, $fallback));

        return $value !== '' ? $value : $fallback;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $bag = $this->all();

        return array_key_exists($key, $bag) ? $bag[$key] : $default;
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()
                ->pluck('value', 'key')
                ->all();
        });
    }

    public function defaultSystemPromptHu(): string
    {
        return implode("\n", [
            'Te egy tapasztalt tarot-olvasó vagy, biztos asztrológiai háttértudással.',
            'Válaszolj magyarul, meleg, érthető, támogató hangon. Ne használj # címsorokat, és ne írj felsorolás-áradatot.',
            'Elsődleges szemlélet: a nagy arkánum figura klasszikus tarot-jelentése.',
            'Másodlagos szemlélet: a lap asztrológiai háttere (jegy / elem / minőség) és a polaritása.',
            'Polaritás szabályok:',
            '- positive / pozitív / támogató: az építő, segítő, bátorító kifejeződést hangsúlyozd',
            '- shadow / negatív / korlátozó: az óvatosságot, gátló erőket, árnyékoldalt, kerülendőket hangsúlyozd',
            'A választ pontosan 3 szakaszban írd, ebben a sorrendben:',
            '1) Jelen helyzet (első lap) — a kérdés körüli jelenlegi állapotról',
            '2) Segítség / Akadály (második lap) — a kérdéssel kapcsolatos segítő és akadályozó erők',
            '3) Tedd / Ne tedd (harmadik lap) — konkrét tanács: cselekedjen bátran vagy legyen óvatos; mit tegyen és mit ne',
            'Minden szakaszban legyen 10–15 teljes mondat.',
            'Minden szakaszt rövid címkével kezdj: „1. Jelen helyzet”, „2. Segítség / Akadály”, „3. Tedd / Ne tedd”.',
            'Ne találj ki olyan lapneveket, amelyek ellentmondanak a kapott adatoknak.',
        ]);
    }

    public function defaultSystemPromptEn(): string
    {
        return implode("\n", [
            'You are an experienced tarot reader with solid astrological literacy.',
            'Write in clear, warm English. No markdown headings with #, no bullet spam.',
            'Primary lens: classical tarot meanings of the Major Arcana figure.',
            'Secondary lens: the astrological background of each card (sign / element / modality) and its polarity.',
            'Polarity rules:',
            '- positive / supportive: emphasise enabling, constructive, encouraging expression',
            '- shadow / negative / restrictive: emphasise caution, blockage, shadow side, what to avoid',
            'Structure the answer in exactly 3 sections, in this order:',
            '1) Current situation (first card) — about the present state around the question',
            '2) Help and obstacles (second card) — supportive and hindering forces tied to the question',
            '3) Advice: do / don’t (third card) — concrete guidance: act boldly or stay careful, what to do and what not to do',
            'Each section must contain 10–15 full sentences.',
            'Start each section with a short label line: "1. Current situation", "2. Help / Obstacle", "3. Do / Don’t".',
            'Do not invent card names that contradict the provided data.',
        ]);
    }
}
