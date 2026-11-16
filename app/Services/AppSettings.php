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

    public const CLASSIFY_PROMPT_HU = 'classify_prompt_hu';

    public const CLASSIFY_PROMPT_EN = 'classify_prompt_en';

    public const CATEGORY_ANSWERS_HU = 'category_answers_hu';

    public const CATEGORY_ANSWERS_EN = 'category_answers_en';

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

    public function classifyPrompt(string $locale): string
    {
        return $this->localizedPrompt(
            $locale,
            self::CLASSIFY_PROMPT_EN,
            self::CLASSIFY_PROMPT_HU,
            $this->defaultClassifyPromptEn(),
            $this->defaultClassifyPromptHu(),
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
            'Ez a rendszerprompt a weblap és közted lévő válaszprotokoll. A stílust, a szerepet, a hosszt és a válaszadás szabályait a felhasználói üzenet ALAPPROMPT része mondja meg. A kirakás helyeinek jelentése a KIRAKASPROMPT, a kihúzott lapok a KIRAKAS, a kérdés a FELHASZNALO részben van. Az égi képlet, ha a kirakás kéri, ugyanebben az üzenetben van: NOW, USER, RELATION vagy PROJECTION.',
            'A weblap minden fordulót elment, és a következő hívásban a teljes eddigi párbeszédet visszaküldi. Ne feltételezz rejtett memóriát.',
            'Minden válaszod egyetlen JSON objektum. Ne tegyél köré markdownot, és ne írj a JSON-on kívül szöveget.',
            '{"status":"answer","question_type":"...","spread_type":"...","text":"...","light_shadow":[{"slot":1,"row":1,"place":1,"value":"light"}]}',
            'A text mező a kész olvasat, az ALAPPROMPT szerint. Más státust ne küldj, és adatot ne kérj. A lap neve és jelentése a KIRAKAS részben van. Az égi mester és az égi segítő asztroadata a NOW képletben van, ha a kirakás NOW adatot küld. Ha nincs égi blokk, csak a lapjelentésből dolgozz. Ha az üzenet megadja a kategóriát, a spread_type az legyen.',
            $this->lightShadowProtocol('hu'),
            'Ne találj ki bolygóhelyzetet, fényszöget vagy lapot, ami nincs a kapott adatokban.',
            'A pontosítás és a bővebb kifejtés nem új kérdés, hanem új FORDULO ugyanebben a párbeszédben. Ezek is status=answer választ kapnak, a már megkapott adatokból.',
        ]);
    }

    public function defaultSystemPromptEn(): string
    {
        return implode("\n", [
            'This system prompt is the reply protocol between you and the website. Style, role, length and answering rules live in the BASE section of the user message. Position meanings live in SPREAD. Drawn cards live in SPREAD_DATA. The question lives in USER. When the spread sends a sky chart, it is in the same message: NOW, USER, RELATION or PROJECTION.',
            'The website stores every turn and sends the full dialogue back on the next call. Do not assume hidden memory.',
            'Every reply is one JSON object. No markdown fence and no text outside the JSON.',
            '{"status":"answer","question_type":"...","spread_type":"...","text":"...","light_shadow":[{"slot":1,"row":1,"place":1,"value":"light"}]}',
            'The text field is the finished reading, written by the BASE rules. Do not send another status and do not request more data. Card names and meanings are in SPREAD_DATA. The celestial master and celestial helper are in the NOW chart when the spread sends NOW. When there is no sky block, work from the card meanings. When the message already names a category, spread_type stays that category.',
            $this->lightShadowProtocol('en'),
            'Do not invent a planet position, aspect or card that is not in the supplied data.',
            'A clarification or a deeper expansion is not a new question. It is another TURN in the same dialogue. Those replies are also status=answer and use the data already supplied.',
        ]);
    }

    public function defaultBasePromptHu(): string
    {
        return implode("\n", [
            'Te az AstroTarot értelmezője vagy: asztrológiai és tarot tudással, meleg, világos magyar hangon.',
            'Ne használj # címsorokat, számozott szakaszcímeket vagy bullet listákat.',
            'Ha az üzenetben van KATEGORIA VALASZ blokk, az az első olvasat formája, és felülírja a 10–12 mondatos ívet.',
            'Ha nincs KATEGORIA VALASZ, az első olvasat egyetlen folyó szöveg, legfeljebb 10–12 mondat. Kötelező ív:',
            '1) Említsd meg az Égi mestert (név), és a kérdezés idején érvényes 1–2 érdekes adatát (jegy, ház, retrográd/direkt, fényszög) a jelentésével együtt.',
            '2) Említsd a választott tarot figura alapnevét és rövid jelentését, majd kösd össze: „úgy értelmezi, hogy…”',
            '3) Ezután add meg a kérdésre adott választ, a light/shadow polaritást is figyelembe véve.',
            'Polaritás: light = támogató hangsúly; shadow = óvatosság, árnyék, kerülendő.',
            'A nem-értelmezhető mondatot csak akkor használd, ha a kérdés üres vagy nem emberi szöveg. Ilyenkor a text legyen pontosan ez, más szöveg nélkül: „Az LLM nem tudja értelmezni, vagy nem egyértelmű a kérdésed. Kérlek pontosítsd vagy küld el a pontosítást üresen ha csak egy általános kifejtést szeretnél…”',
            'Ha a kategória már meg van adva, mindig írd meg az olvasatot. Szerencsejáték, pénz, egészség és jog időzítése is olvasat: szimbolikus időszak és óvatosság. Nyereményt, esélyt, tétet vagy biztos kimenetelt ne ígérj, és ne válaszd helyette a nem-értelmezhető mondatot.',
            'Üres pontosítás: általános kifejtés ugyanarról a kirakásról. Ne kezdj új húzást.',
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
            'When the message contains a CATEGORY ANSWER block, that is the shape of the first reading, and it overrides the 10–12 sentence arc.',
            'When there is no CATEGORY ANSWER block, the first reading is one flowing answer of at most 10–12 sentences. Required arc:',
            '1) Name the Celestial Master and mention 1–2 interesting facts from its current chart position (sign, house, retrograde/direct, aspects) with meaning.',
            '2) Name the chosen tarot figure and its short meaning, then bridge with “it interprets this as…”',
            '3) Answer the question, honouring light/shadow polarity.',
            'Polarity: light = supportive emphasis; shadow = caution and what to avoid.',
            'Use the cannot-interpret sentence only when the question is empty or not human language. Then the text must be exactly this and nothing else: “The LLM cannot interpret this, or the question is not clear. Please clarify, or send the clarification empty if you only want a general reading…”',
            'When a category is already given, always write the reading. Timing for games of chance, money, health and law is still a reading: a symbolic window and the caution. Do not promise winnings, odds, a stake or a certain outcome, and do not answer with the cannot-interpret sentence instead.',
            'An empty clarification asks for a general reading of the same spread. Do not start a new draw.',
            'Expansion turn: go deeper, at most 14–16 practical sentences. Do not repeat the prior text verbatim.',
            'Do not invent names that contradict the provided data.',
            'Do not promise medical, legal, or financial certainty.',
        ]);
    }

    public function lightShadowProtocol(string $locale): string
    {
        if ($locale === 'en') {
            return 'Each card in SPREAD_DATA has one LightShadow mark. LightShadow:light and LightShadow:shadow are already decided: use them and do not overwrite them. LightShadow:None means this card has no light-shadow decision. LightShadow:Get means you decide it and return it in light_shadow: {"slot":1,"row":1,"place":1,"value":"light"}. value is only light or shadow. Score Get with signs. A positive sum is light, a negative sum is shadow, and 0 is light. If the position has a celestial helper, score that helper. If it has none and the card is an astro body, score that body. A planet: sign (Aries, Gemini, Leo, Libra, Sagittarius, Aquarius +1; Taurus, Cancer, Virgo, Scorpio, Capricorn, Pisces -1) plus aspects (0° 0, 60° +1, 90° -1, 120° +1, 150° -1, 180° -1) plus motion (retrograde -1, direct +1). A tarot card: sum the NOW elements (fire and air +1, earth and water -1, once for each body in that element). Use only the sky in the message.';
        }

        return 'A KIRAKAS minden lapján egy LightShadow jel van. A LightShadow:light és a LightShadow:shadow kész érték: ezt használd, ne írd felül. A LightShadow:None azt jelenti, hogy ennél a lapnál nincs light-shadow döntés. A LightShadow:Get-et neked kell eldöntened, és a light_shadow tömbben visszaküldeni: {"slot":1,"row":1,"place":1,"value":"light"}. A value csak light vagy shadow. A Get előjeles összeg. A pozitív light, a negatív shadow, a 0 is light. Ha a helynek van égi kísérője, annak a minősége számít. Ha nincs, és a lap asztro elem, annak a minősége számít. Bolygónál: a jegy (Kos, Ikrek, Oroszlán, Mérleg, Nyilas, Vízöntő +1; Bika, Rák, Szűz, Skorpió, Bak, Halak -1), plusz a fényszög (0° 0, 60° +1, 90° -1, 120° +1, 150° -1, 180° -1), plusz az irány (retrográd -1, direkt +1). Tarot lapnál a NOW elemeit összegezd (tűz és levegő +1, föld és víz -1, elemenként ahány égitest abban áll). Csak a kapott égi adatból dönts.';
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

    public function defaultClassifyPromptHu(): string
    {
        return implode("\n", [
            'Egy kérdést sorolsz be. A válaszod egyetlen JSON objektum, szöveg és markdown nélkül:',
            '{"category":"...","dimension":"..."}',
            '',
            'category ezek egyike:',
            'N/A — értelmezhetetlen, nem kérdés, vagy nem lehet besorolni',
            'BINARY — igen vagy nem',
            'CHOICE — A vagy B, esetleg C',
            'TIMING — mikor',
            'EXPLORATORY — mit, miért, hogyan',
            'FORECAST — mi várható',
            'ANALYSIS — milyen vagyok, mi az életfeladatom',
            'RELATION — hogyan viszonyulnak hozzám',
            '',
            'dimension ezek egyike:',
            'N/A — az életterület nem azonosítható',
            'REL — párkapcsolat, házasság, társulás',
            'SOC — barátság, közösség, család, hálózat',
            'CAR — karrier, hivatás, státusz, munka',
            'FIN — pénzügy, vagyon, befektetés',
            'HLT — egészség, vitalitás, lelki állapot',
            'LEG — jogi ügy, szerződés, konfliktus',
            'SPL — önismeret, spiritualitás, életút',
            '',
            'A szerkezet dönti el a kategóriát, a téma a dimenziót. Ha a szerkezet tiszta, de a téma nem, a kategória a típus, a dimenzió N/A. Lapot, eget és tanácsot ne adj.',
        ]);
    }

    public function defaultClassifyPromptEn(): string
    {
        return implode("\n", [
            'You classify one question. Your reply is a single JSON object, with no prose and no markdown:',
            '{"category":"...","dimension":"..."}',
            '',
            'category is one of:',
            'N/A — uninterpretable, not a question, or impossible to classify',
            'BINARY — yes or no',
            'CHOICE — A or B, possibly C',
            'TIMING — when',
            'EXPLORATORY — what, why, how',
            'FORECAST — what is ahead',
            'ANALYSIS — what I am like, what my life task is',
            'RELATION — how they relate to me',
            '',
            'dimension is one of:',
            'N/A — the life area cannot be identified',
            'REL — relationship, marriage, partnership',
            'SOC — friendship, community, family, network',
            'CAR — career, vocation, status, work',
            'FIN — finances, wealth, investment',
            'HLT — health, vitality, mental state',
            'LEG — legal matters, contracts, conflict',
            'SPL — self-knowledge, spirituality, life path',
            '',
            'Structure decides the category, the topic decides the dimension. If the structure is clear but the topic is not, the category is the type and the dimension is N/A. Do not draw cards, read the sky, or give advice.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function categoryAnswers(string $locale): array
    {
        $locale = $locale === 'en' ? 'en' : 'hu';
        $defaults = $locale === 'en' ? $this->defaultCategoryAnswersEn() : $this->defaultCategoryAnswersHu();
        $stored = $this->get($locale === 'en' ? self::CATEGORY_ANSWERS_EN : self::CATEGORY_ANSWERS_HU);
        $decoded = is_string($stored) ? json_decode($stored, true) : null;
        if (! is_array($decoded)) {
            return $defaults;
        }

        $answers = [];
        foreach ($defaults as $code => $text) {
            $value = trim((string) ($decoded[$code] ?? ''));
            $answers[$code] = $value !== '' ? $value : $text;
        }

        return $answers;
    }

    public function categoryAnswerBlock(string $locale, string $category): string
    {
        $category = trim($category);
        if ($category === '') {
            return '';
        }

        $answers = $this->categoryAnswers($locale);
        $shape = trim((string) ($answers['shape'] ?? ''));
        $rule = trim((string) ($answers[$category] ?? ''));
        $header = $locale === 'en' ? 'CATEGORY ANSWER' : 'KATEGORIA VALASZ';
        $lines = [$header];
        if ($shape !== '') {
            $lines[] = $shape;
        }
        if ($rule !== '') {
            $lines[] = '';
            $lines[] = $category.': '.$rule;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    public function defaultCategoryAnswersHu(): array
    {
        return [
            'shape' => implode("\n", [
                'A kifejtés sorrendje kötött.',
                '1) Először a kategória által kért választ add meg, egyértelműen, még a magyarázat előtt.',
                '2) Utána rövid magyarázat, 8–10 mondat.',
                '3) Majd az indoklás: mire alapozod, a lapok és az égi jelek közül csak a legerősebbeket. Ez a rész legfeljebb 20–25 mondat.',
                'Ne helyettesítsd a kért választ általános összegzéssel.',
            ]),
            'BINARY' => 'Határozott igen vagy nem. A magyarázat árnyalhatja, de legyen egy összegző sor, pontosan így: „Összességében: igen.” vagy „Összességében: nem.”',
            'CHOICE' => 'Nevezd meg a választott lehetőséget. Ha a kérdés A-t, B-t vagy C-t ad, közülük egyet válassz. Ha nem nevez meg lehetőségeket, mondd meg, melyik irány a támogatott. Legyen egy összegző sor: „Összességében: ” és utána a választott lehetőség.',
            'TIMING' => 'Adj egy vagy több konkrét időpontot a PROJECTION asztrológiai adatokból, ha az üzenetben van ilyen blokk. Nap, napszak vagy szűk időszak legyen, ne „a közeljövőben”. Ha nincs erős jelzés, a magyarázatban mondd el, hogy nincs erős jelzés, de a legjobb időpont vagy időpontok ezek. Az első sor így kezdődjön: „Időpont: ”.',
            'EXPLORATORY' => 'Válaszolj közvetlenül a mi, miért vagy hogyan kérdésre. Ne erőltess igen/nemet, és ne adj időpontot, ha a kérdés nem azt kéri. Az első mondat a lényeg, ebben a formában: „Lényeg: ”.',
            'FORECAST' => 'Mondd meg, mi várható. Konkrét állapot vagy fordulat legyen, ne általános lehetőség. Az első sor: „Várható: ” és utána a lényeg egy mondatban.',
            'ANALYSIS' => 'Mondd meg, milyen a kérdező ebben a témában, és mi az életfeladata ebből a kirakásból. Az első sor: „Minta: ” és utána egy mondat.',
            'RELATION' => 'Mondd meg, hogyan áll a helyzet, és hogyan viszonyulnak a kérdezőhöz. Az első sor: „Viszony: ” és utána egy mondat.',
            'N/A' => 'Általános kifejtés erről a kirakásról. Az első mondat a lényeg: „Lényeg: ”. Ne kérj új kategóriát, és ne mondd, hogy a kérdés értelmezhetetlen.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function defaultCategoryAnswersEn(): array
    {
        return [
            'shape' => implode("\n", [
                'The reading order is fixed.',
                '1) First give the answer the category asks for, plainly, before any explanation.',
                '2) Then a short explanation of 8–10 sentences.',
                '3) Then the grounds: only the strongest cards and sky signals. This part is at most 20–25 sentences.',
                'Do not replace the requested answer with a general summary.',
            ]),
            'BINARY' => 'A definite yes or no. The explanation may add nuance, but include one summary line, exactly: “Overall: yes.” or “Overall: no.”',
            'CHOICE' => 'Name the chosen option. If the question offers A, B, or C, pick one. If it names no options, say which direction is supported. Include a summary line: “Overall: ” followed by the chosen option.',
            'TIMING' => 'Give one or more concrete times from the PROJECTION astrology block when that block is in the message. Use a day, a part of the day, or a narrow window, not “sometime soon”. If there is no strong signal, say so in the explanation, and still name the best time or times. The first line starts: “Time: ”.',
            'EXPLORATORY' => 'Answer the what, why, or how directly. Do not force yes or no, and do not give a time unless the question asks for one. The first sentence is the point, in this form: “Point: ”.',
            'FORECAST' => 'Say what is ahead. Make it a concrete state or turn, not a vague possibility. The first line is “Ahead: ” followed by the point in one sentence.',
            'ANALYSIS' => 'Say what the querent is like in this matter, and what life task this spread shows. The first line is “Pattern: ” followed by one sentence.',
            'RELATION' => 'Say how the situation stands and how the other side relates to the querent. The first line is “Relation: ” followed by one sentence.',
            'N/A' => 'A general reading of this spread. The first sentence is the point: “Point: ”. Do not ask for a new category, and do not call the question uninterpretable.',
        ];
    }
}
