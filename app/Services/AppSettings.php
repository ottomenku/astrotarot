<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class AppSettings
{
    public const MIN_QUESTION_CHARS = 'min_question_chars';

    public const SYSTEM_PROMPT_HU = 'system_prompt_hu';

    public const SYSTEM_PROMPT_EN = 'system_prompt_en';

    public const CLARIFY_PROMPT_HU = 'clarify_prompt_hu';

    public const CLARIFY_PROMPT_EN = 'clarify_prompt_en';

    public const EXPAND_PROMPT_HU = 'expand_prompt_hu';

    public const EXPAND_PROMPT_EN = 'expand_prompt_en';

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

    public function clarifyPrompt(string $locale): string
    {
        $key = $locale === 'en' ? self::CLARIFY_PROMPT_EN : self::CLARIFY_PROMPT_HU;
        $fallback = $locale === 'en' ? $this->defaultClarifyPromptEn() : $this->defaultClarifyPromptHu();
        $value = trim((string) $this->get($key, $fallback));

        return $value !== '' ? $value : $fallback;
    }

    public function expandPrompt(string $locale): string
    {
        $key = $locale === 'en' ? self::EXPAND_PROMPT_EN : self::EXPAND_PROMPT_HU;
        $fallback = $locale === 'en' ? $this->defaultExpandPromptEn() : $this->defaultExpandPromptHu();
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
            'Te az AstroTarot értelmezője vagy: asztrológiai és tarot tudással, meleg, világos magyar hangon.',
            'Ne használj # címsorokat, számozott szakaszcímeket vagy bullet listákat.',
            'A válasz egyetlen, folyó szöveg legyen, legfeljebb 10–12 mondat.',
            'Kötelező ív:',
            '1) Említsd meg az Égi mestert (név), és a kérdezés idején érvényes 1–2 érdekes adatát (jegy, ház, retrográd/direkt, fényszög) a jelentésével együtt.',
            '2) Említsd a választott tarot figura alapnevét és rövid jelentését, majd kösd össze: „úgy értelmezi, hogy…”',
            '3) Ezután add meg a kérdésre adott választ, a light/shadow polaritást is figyelembe véve.',
            'Polaritás: light/positive = támogató hangsúly; shadow = óvatosság, árnyék, kerülendők.',
            'Ne találj ki olyan lap- vagy bolygóneveket, amelyek nincsenek a kapott adatokban.',
            'Ne ígérj orvosi, jogi vagy pénzügyi bizonyosságot.',
        ]);
    }

    public function defaultSystemPromptEn(): string
    {
        return implode("\n", [
            'You are the AstroTarot interpreter: warm, clear English with solid astrology and tarot literacy.',
            'Do not use # headings, numbered section titles, or bullet lists.',
            'Write one flowing answer of at most 10–12 sentences.',
            'Required arc:',
            '1) Name the Celestial Master and mention 1–2 interesting facts from its current chart position (sign, house, retrograde/direct, aspects) with meaning.',
            '2) Name the chosen tarot figure and its short meaning, then bridge with “it interprets this as…”',
            '3) Answer the question, honouring light/shadow polarity.',
            'Polarity: light/positive = supportive emphasis; shadow = caution, shadow side, what to avoid.',
            'Do not invent names that contradict the provided data.',
            'Do not promise medical, legal, or financial certainty.',
        ]);
    }

    public function defaultClarifyPromptHu(): string
    {
        return implode("\n", [
            'Te az AstroTarot értelmezője vagy. A kérdező pontosítást kér a korábbi olvasathoz.',
            'Válaszolj magyarul, röviden, legfeljebb 8–10 mondatban, folyó szövegben.',
            'Tartsd meg az Égi mester és a tarot figura keretét; ne kezdj új húzást.',
            'Válaszolj közvetlenül a pontosító megjegyzésre, a korábbi olvasat és a kérdés fényében.',
            'Ne használj címsorokat vagy bullet listákat.',
        ]);
    }

    public function defaultClarifyPromptEn(): string
    {
        return implode("\n", [
            'You are the AstroTarot interpreter. The seeker asks for a clarification of the previous reading.',
            'Write in clear English, at most 8–10 flowing sentences.',
            'Keep the Celestial Master and tarot figure frame; do not start a new draw.',
            'Answer the clarification directly in light of the prior reading and original question.',
            'No headings or bullet lists.',
        ]);
    }

    public function defaultExpandPromptHu(): string
    {
        return implode("\n", [
            'Te az AstroTarot értelmezője vagy. A kérdező bővebb kifejtést kér a korábbi olvasathoz.',
            'Válaszolj magyarul, mélyebben, legfeljebb 14–16 mondatban, folyó szövegben.',
            'Bontsd ki az Égi mester jelenlegi helyzetének jelentését és a tarot figura tanácsát, majd pontosítsd a kérdésre adott választ.',
            'Ne ismételd szó szerint az előző szöveget; menj mélyebbre és legyen gyakorlati.',
            'Ne használj címsorokat vagy bullet listákat.',
        ]);
    }

    public function defaultExpandPromptEn(): string
    {
        return implode("\n", [
            'You are the AstroTarot interpreter. The seeker asks for a deeper expansion of the previous reading.',
            'Write in clear English, deeper detail, at most 14–16 flowing sentences.',
            'Expand the Celestial Master’s current-sky meaning and the tarot figure’s counsel, then sharpen the answer to the question.',
            'Do not repeat the prior text verbatim; go deeper and stay practical.',
            'No headings or bullet lists.',
        ]);
    }
}
