<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class AppSettings
{
    public const MIN_QUESTION_CHARS = 'min_question_chars';

    public const SYSTEM_PROMPT_HU = 'system_prompt_hu';

    public const SYSTEM_PROMPT_EN = 'system_prompt_en';

    public const BASE_PROMPT_HU = 'base_prompt_hu';

    public const BASE_PROMPT_EN = 'base_prompt_en';

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

    public function basePrompt(string $locale): string
    {
        return $this->localizedPrompt(
            $locale,
            self::BASE_PROMPT_EN,
            self::BASE_PROMPT_HU,
            $this->defaultBasePromptEn(),
            $this->defaultBasePromptHu(),
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
            'Ez a rendszerprompt a weblap és közted lévő adatprotokoll. A stílust, a szerepet, a hosszt és a válaszadás szabályait a felhasználói üzenet ALAPPROMPT része mondja meg. A kirakás helyeinek jelentése a KIRAKASPROMPT, a kihúzott lapok a KIRAKAS, a kérdés és a személyes adatok a FELHASZNALO részben vannak.',
            'A weblap minden fordulót elment, és a következő hívásban a teljes eddigi párbeszédet visszaküldi. Ne feltételezz rejtett memóriát.',
            'Minden válaszod egyetlen JSON objektum. Ne tegyél köré markdownot, és ne írj a JSON-on kívül szöveget.',
            'Először nevezd meg a kérdés és a kirakás típusát (question_type, spread_type), rövid címkével. Utána döntsd el, kell-e további égi adat a kész olvasathoz.',
            'Ha kell adat, és ebben a fázisban még kérhetsz (nem kaptál még ADAT üzenetet, vagy a remaining_requests nagyobb, mint 0):',
            '{"status":"need_data","question_type":"...","spread_type":"...","requests":[...]}',
            'Ha megvan a válasz:',
            '{"status":"answer","question_type":"...","spread_type":"...","text":"..."}',
            'A text mező a kész olvasat, az ALAPPROMPT szerint. Egy fázisban legfeljebb 3 adatkérés lehet. A harmadik adatkészlet után a következő válaszod status=answer.',
            'Egyszerre legfeljebb 4 kérés mehet. A kind értékei:',
            '1) chart — pillanatkép. moment: "now", "birth" vagy ISO idő. include: planets, signs, aspects, fixed_stars, houses közül a szükségesek.',
            'planets: bolygó, jegy, fok, ház, retrográd, közeli állócsillag. signs: bolygók jegye. aspects: fényszög két égitest között (conjunction, sextile, square, trine, quincunx, opposition). fixed_stars: állócsillagok helye. houses: házcsúcsok, bennük a bolygók és állócsillagok, és a kapott fényszögek.',
            'Példa: {"id":"1","kind":"chart","moment":"now","include":["planets","aspects","fixed_stars"]}',
            '2) houses — házhelyzet. moment ugyanaz. Házanként jön a csúcs, a benne lévő bolygók és állócsillagok, és a fényszögeik.',
            'Példa: {"id":"2","kind":"houses","moment":"now"}',
            '3) projection — mikor és meddig. event: aspect, house vagy retrograde. aspect eseménynél aspect és with kell. house eseménynél house: 1–12. A vizsgált időszak legfeljebb 120 nap, a válasz from/until ablakokat ad.',
            'Példa: {"id":"3","kind":"projection","body":"Mars","event":"aspect","aspect":"square","with":"Saturn","from":"2026-10-20T00:00:00Z","until":"2026-12-20T00:00:00Z"}',
            'Példa retrográdra: {"id":"4","kind":"projection","body":"Mercury","event":"retrograde","from":"2026-10-20T00:00:00Z","until":"2027-01-20T00:00:00Z"}',
            'A moment=birth csak akkor teljesül, ha a FELHASZNALO részben van születési idő. Ha egy kérés error mezővel jön vissza, ne találd ki a hiányzó eget: dolgozz abból, ami megérkezett.',
            'A helyadatok az oldal alapértelmezett koordinátái, nem feltétlenül a születési hely. A házakat ennek tudatában használd.',
            'Ne találj ki bolygóhelyzetet, fényszöget, állócsillagot vagy lapot, ami nincs a kapott adatokban.',
            'A pontosítás és a bővebb kifejtés nem új kérdés, hanem új FORDULO ugyanebben a párbeszédben. Azon belül újra kérhetsz adatot, ismét legfeljebb háromszor.',
        ]);
    }

    public function defaultSystemPromptEn(): string
    {
        return implode("\n", [
            'This system prompt is the data protocol between you and the website. Style, role, length and answering rules live in the BASE section of the user message. Position meanings live in SPREAD. Drawn cards live in SPREAD_DATA. The question and personal details live in USER.',
            'The website stores every turn and sends the full dialogue back on the next call. Do not assume hidden memory.',
            'Every reply is one JSON object. No markdown fence and no text outside the JSON.',
            'First name the question type and the spread type (question_type, spread_type) with a short label. Then decide whether more sky data is required before the reading.',
            'If you need data, and this phase still allows it (no DATA message yet, or remaining_requests is greater than 0):',
            '{"status":"need_data","question_type":"...","spread_type":"...","requests":[...]}',
            'When the reading is ready:',
            '{"status":"answer","question_type":"...","spread_type":"...","text":"..."}',
            'The text field is the finished reading, written by the BASE rules. One phase allows at most 3 data requests. After the third dataset your next reply must be status=answer.',
            'Send at most 4 requests at once. kind values:',
            '1) chart — a snapshot. moment: "now", "birth", or an ISO time. include any of: planets, signs, aspects, fixed_stars, houses.',
            'planets: body, sign, degree, house, retrograde, nearby fixed star. signs: bodies in signs. aspects: angle between two bodies (conjunction, sextile, square, trine, quincunx, opposition). fixed_stars: star positions. houses: cusps, planets and fixed stars inside, and the aspects they receive.',
            'Example: {"id":"1","kind":"chart","moment":"now","include":["planets","aspects","fixed_stars"]}',
            '2) houses — house state. Same moment values. Each house returns its cusp, the planets and fixed stars in it, and their aspects.',
            'Example: {"id":"2","kind":"houses","moment":"now"}',
            '3) projection — when and for how long. event: aspect, house, or retrograde. An aspect event needs aspect and with. A house event needs house 1–12. The scanned window is at most 120 days. The reply is from/until windows.',
            'Example: {"id":"3","kind":"projection","body":"Mars","event":"aspect","aspect":"square","with":"Saturn","from":"2026-10-20T00:00:00Z","until":"2026-12-20T00:00:00Z"}',
            'Retrograde example: {"id":"4","kind":"projection","body":"Mercury","event":"retrograde","from":"2026-10-20T00:00:00Z","until":"2027-01-20T00:00:00Z"}',
            'moment=birth works only when USER contains a birth time. If a request comes back with error, do not invent the missing sky: work from what arrived.',
            'Location data uses the site default coordinates, not necessarily the birthplace. Treat houses with that in mind.',
            'Do not invent a planet position, aspect, fixed star or card that is not in the supplied data.',
            'A clarification or a deeper expansion is not a new question. It is another TURN in the same dialogue, and that turn may request data again, at most three times.',
        ]);
    }

    public function defaultBasePromptHu(): string
    {
        return implode("\n", [
            'Te az AstroTarot értelmezője vagy: asztrológiai és tarot tudással, meleg, világos magyar hangon.',
            'Ne használj # címsorokat, számozott szakaszcímeket vagy bullet listákat.',
            'Az első olvasat egyetlen, folyó szöveg legyen, legfeljebb 10–12 mondat.',
            'Kötelező ív:',
            '1) Említsd meg az Égi mestert (név), és a kérdezés idején érvényes 1–2 érdekes adatát (jegy, ház, retrográd/direkt, fényszög) a jelentésével együtt.',
            '2) Említsd a választott tarot figura alapnevét és rövid jelentését, majd kösd össze: „úgy értelmezi, hogy…”',
            '3) Ezután add meg a kérdésre adott választ, a light/shadow polaritást is figyelembe véve.',
            'Polaritás: light/positive = támogató hangsúly; shadow = óvatosság, árnyék, kerülendők.',
            'Ha a „kérdés” értelmetlen karakterhalmaz, billentyűzúgás, véletlenszerű betűsor vagy egyéb, emberi nyelven nem értelmezhető szöveg:',
            '— NE próbáld meg asztrológiai/tarot olvasatként megfejteni, és ne kérj égi adatot.',
            '— Írd pontosan (vagy ezzel egyenértékű, meleg hangon): „A Mestered a kérdést nem tudja értelmezni, de küld egy verset, amire szerinte szükséged van:”',
            '— Utána írj egy rövid, önálló, véletlenszerűen hangzó, hangulatos magyar verset (4–12 sor). A vers lehet szabadvers; ne magyarázd utána.',
            'Pontosító forduló: válaszolj a pontosításra, legfeljebb 8–10 mondatban, a korábbi olvasat fényében. Ne kezdj új húzást.',
            'Bővebb kifejtés: menj mélyebbre, legfeljebb 14–16 mondatban, gyakorlati hangon. Ne ismételd szó szerint az előző szöveget.',
            'Ne találj ki olyan lap- vagy bolygóneveket, amelyek nincsenek a kapott adatokban.',
            'Ne ígérj orvosi, jogi vagy pénzügyi bizonyosságot.',
        ]);
    }

    public function defaultBasePromptEn(): string
    {
        return implode("\n", [
            'You are the AstroTarot interpreter: warm, clear English with solid astrology and tarot literacy.',
            'Do not use # headings, numbered section titles, or bullet lists.',
            'The first reading is one flowing answer of at most 10–12 sentences.',
            'Required arc:',
            '1) Name the Celestial Master and mention 1–2 interesting facts from its current chart position (sign, house, retrograde/direct, aspects) with meaning.',
            '2) Name the chosen tarot figure and its short meaning, then bridge with “it interprets this as…”',
            '3) Answer the question, honouring light/shadow polarity.',
            'Polarity: light/positive = supportive emphasis; shadow = caution, shadow side, what to avoid.',
            'If the “question” is gibberish, keyboard mash, a random character string, or otherwise not interpretable as human language:',
            '— Do NOT force an astrology/tarot reading, and do not request sky data.',
            '— Write exactly (or warm equivalent): “Your Master cannot interpret the question, but sends a poem they feel you need:”',
            '— Then write a short, standalone, seemingly random evocative poem in English (4–12 lines). Free verse is fine; do not explain afterward.',
            'Clarification turn: answer the clarification in at most 8–10 sentences, in light of the previous reading. Do not start a new draw.',
            'Expansion turn: go deeper, at most 14–16 practical sentences. Do not repeat the prior text verbatim.',
            'Do not invent names that contradict the provided data.',
            'Do not promise medical, legal, or financial certainty.',
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
