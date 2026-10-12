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

    public const SITE_ENABLED = 'site_enabled';

    public const REGISTRATION_ENABLED = 'registration_enabled';

    public const TERMS_HU = 'legal_terms_hu';

    public const TERMS_EN = 'legal_terms_en';

    public const PRIVACY_HU = 'legal_privacy_hu';

    public const PRIVACY_EN = 'legal_privacy_en';

    public const LLM_ENABLED = 'llm_enabled';

    public const LLM_ROUTE = 'llm_route';

    public const LLM_ROUTE_OLLAMA_SMALL = 'ollama_small';

    public const LLM_ROUTE_OLLAMA_LARGE = 'ollama_large';

    public const LLM_ROUTE_OPENAI = 'openai';

    public const PAID_FEATURES_ENABLED = 'paid_features_enabled';

    /** @var list<string> */
    public const LLM_ROUTES = [
        self::LLM_ROUTE_OLLAMA_SMALL,
        self::LLM_ROUTE_OLLAMA_LARGE,
        self::LLM_ROUTE_OPENAI,
    ];

    private const CACHE_KEY = 'app.settings.bag';

    public function minQuestionChars(): int
    {
        $value = (int) $this->get(self::MIN_QUESTION_CHARS, '10');

        return max(1, min(280, $value));
    }

    public function siteEnabled(): bool
    {
        return $this->flag(self::SITE_ENABLED, true);
    }

    public function registrationEnabled(): bool
    {
        return $this->flag(self::REGISTRATION_ENABLED, true);
    }

    public function paidFeaturesEnabled(): bool
    {
        return $this->flag(self::PAID_FEATURES_ENABLED, true);
    }

    public function terms(string $locale): string
    {
        return $this->localizedPrompt(
            $locale,
            self::TERMS_EN,
            self::TERMS_HU,
            $this->defaultTermsEn(),
            $this->defaultTermsHu(),
        );
    }

    public function privacy(string $locale): string
    {
        return $this->localizedPrompt(
            $locale,
            self::PRIVACY_EN,
            self::PRIVACY_HU,
            $this->defaultPrivacyEn(),
            $this->defaultPrivacyHu(),
        );
    }

    public function llmEnabled(): bool
    {
        return $this->flag(self::LLM_ENABLED, true);
    }

    public function llmRoute(): ?string
    {
        $value = (string) $this->get(self::LLM_ROUTE, '');

        return in_array($value, self::LLM_ROUTES, true) ? $value : null;
    }

    public function inferredLlmRoute(): string
    {
        $driver = strtolower((string) config('services.openai.driver', 'openai'));
        if ($driver !== 'ollama') {
            return self::LLM_ROUTE_OPENAI;
        }

        $model = (string) config('services.openai.model', '');
        $small = (string) config('services.openai.ollama_small_model', 'llama3.2');

        return $model === $small
            ? self::LLM_ROUTE_OLLAMA_SMALL
            : self::LLM_ROUTE_OLLAMA_LARGE;
    }

    public function effectiveLlmRoute(): string
    {
        return $this->llmRoute() ?? $this->inferredLlmRoute();
    }

    private function flag(string $key, bool $default): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    public function systemPrompt(string $locale): string
    {
        return $this->localizedPrompt(
            $locale,
            self::SYSTEM_PROMPT_EN,
            self::SYSTEM_PROMPT_HU,
            $this->defaultSystemPromptEn(),
            $this->defaultSystemPromptHu(),
        );
    }

    public function clarifyPrompt(string $locale): string
    {
        return $this->localizedPrompt(
            $locale,
            self::CLARIFY_PROMPT_EN,
            self::CLARIFY_PROMPT_HU,
            $this->defaultClarifyPromptEn(),
            $this->defaultClarifyPromptHu(),
        );
    }

    public function expandPrompt(string $locale): string
    {
        return $this->localizedPrompt(
            $locale,
            self::EXPAND_PROMPT_EN,
            self::EXPAND_PROMPT_HU,
            $this->defaultExpandPromptEn(),
            $this->defaultExpandPromptHu(),
        );
    }

    private function localizedPrompt(
        string $locale,
        string $enKey,
        string $huKey,
        string $enFallback,
        string $huFallback,
    ): string {
        $fallback = $locale === 'en' ? $enFallback : $huFallback;
        $value = trim((string) $this->get($locale === 'en' ? $enKey : $huKey, $fallback));

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
            'Ha a „kérdés” értelmetlen karakterhalmaz, billentyűzúgás, véletlenszerű betűsor vagy egyéb, emberi nyelven nem értelmezhető szöveg:',
            '— NE próbáld meg asztrológiai/tarot olvasatként megfejteni.',
            '— Írd pontosan (vagy ezzel egyenértékű, meleg hangon): „A Mestered a kérdést nem tudja értelmezni, de küld egy verset, amire szerinte szükséged van:”',
            '— Utána írj egy rövid, önálló, véletlenszerűen hangzó, hangulatos magyar verset (4–12 sor). A vers lehet szabadvers; ne magyarázd utána.',
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
            'If the “question” is gibberish, keyboard mash, a random character string, or otherwise not interpretable as human language:',
            '— Do NOT force an astrology/tarot reading.',
            '— Write exactly (or warm equivalent): “Your Master cannot interpret the question, but sends a poem they feel you need:”',
            '— Then write a short, standalone, seemingly random evocative poem in English (4–12 lines). Free verse is fine; do not explain afterward.',
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

    public function defaultTermsHu(): string
    {
        return implode("\n", [
            'MINTA — Általános Szerződési Feltételek (ÁSZF)',
            '',
            'Ez a szöveg mintapélda, és a végleges jogi tartalomra cserélendő.',
            '',
            '1. Az AstroTarot szórakoztató, önismereti szolgáltatás. Az olvasatok nem minősülnek orvosi, jogi vagy pénzügyi tanácsadásnak.',
            '2. A regisztrációval a felhasználó fiókot hoz létre, és a szolgáltatás használatára a mindenkori ÁSZF vonatkozik.',
            '3. A kreditfelhasználás, a csomagok és a napi bónuszok a fiókbeállítások szerint érvényesek.',
            '4. Fenntartjuk a jogot a szolgáltatás szüneteltetésére, fejlesztésére és a feltételek frissítésére.',
            '5. Kérdés esetén az üzemeltető a weboldalon megadott elérhetőségeken kereshető.',
        ]);
    }

    public function defaultTermsEn(): string
    {
        return implode("\n", [
            'SAMPLE — Terms and Conditions',
            '',
            'This text is a placeholder and must be replaced with the final legal terms.',
            '',
            '1. AstroTarot is an entertainment and self-reflection service. Readings are not medical, legal or financial advice.',
            '2. Registration creates an account governed by these terms as updated from time to time.',
            '3. Credits, packages and daily bonuses apply according to the account settings.',
            '4. We may pause, improve or update the service and these terms.',
            '5. Contact the operator via the details published on the website.',
        ]);
    }

    public function defaultPrivacyHu(): string
    {
        return implode("\n", [
            'MINTA — Adatkezelési tájékoztató',
            '',
            'Ez a szöveg mintapélda, és a végleges adatkezelési tájékoztatóra cserélendő.',
            '',
            '1. Kezelt adatok: név, e-mail cím, jelszóhash vagy közösségi azonosító, kredit- és csomagadatok, kérdések és olvasatok.',
            '2. Cél: fiók működtetése, bejelentkezés, személyre szabott tarot-olvasat, ügyfélszolgálat.',
            '3. Jogalap (minta): szerződés teljesítése és a felhasználó hozzájárulása a regisztrációkor.',
            '4. Tárolás: a szolgáltatás működéséhez szükséges ideig, a fiók törléséig vagy a jogszabályi megőrzésig.',
            '5. A Beállítások menüben megtekintheted a rólad tárolt adatokat, törölheted a beszélgetéseidet, és törlési kérelmet küldhetsz. A fizetési, számviteli és egyéb jogszabályi megőrzés alá eső adatokat (például kreditigénylések) a törvényi határidőig megtartjuk.',
        ]);
    }

    public function defaultPrivacyEn(): string
    {
        return implode("\n", [
            'SAMPLE — Privacy notice',
            '',
            'This text is a placeholder and must be replaced with the final privacy notice.',
            '',
            '1. Data processed: name, email, password hash or social ID, credit and plan data, questions and readings.',
            '2. Purpose: account operation, login, personalised tarot readings, support.',
            '3. Legal basis (sample): performance of a contract and consent given at registration.',
            '4. Retention: as long as needed to run the service, until account deletion, or as required by law.',
            '5. In Settings you can view your stored data, delete your conversations, and send a deletion request. Payment, accounting and other legally retained records (such as credit requests) are kept for the statutory period.',
        ]);
    }
}
