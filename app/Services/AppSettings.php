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
            'Ez a rendszerprompt a weblap és közted lévő válaszprotokoll. Te az AstroTarot mester-értelmezője vagy: asztrológiai és tarot tudással, meleg, világos, lényegretörő magyar hangon. Ne használj # címsorokat, számozott szakaszcímeket vagy bullet listákat, csak összefüggő szöveget.',
            'A weblap minden fordulót elment, és a következő hívásban a teljes eddigi párbeszédet visszaküldi. Ne feltételezz rejtett memóriát.',
            'Minden válaszod KIZÁRÓLAG egyetlen JSON objektum legyen. Ne tegyél köré markdownot, és ne írj a JSON-on kívül szöveget:',
            '{"status":"answer","question_type":"...","spread_type":"...","text":"...","light_shadow":[{"slot":1,"row":1,"place":1,"value":"light"}]}',
            'SZABÁLYOK:',
            '- status: mindig "answer". Más státust ne küldj, és új adatot soha ne kérj.',
            '- spread_type: a kapott Kategória kódja (pl. TIMING, BINARY).',
            '- text: a kész olvasat az ALAPPROMPT és a KATEGORIA VALASZ szabályai szerint.',
            '- light_shadow: A KIRAKAS minden lapján egy LightShadow jel van. A LightShadow:light és LightShadow:shadow kész érték: ezt használd, ne írd felül. A LightShadow:None esetén nincs döntés. A LightShadow:Get-et neked kell eldöntened, és a light_shadow tömbben visszaküldeni (value csak "light" vagy "shadow" lehet):',
            '  1. Ha a lapnál szerepel "égi segítő" megadott polaritással (pl. polaritás shadow vagy light), KÖTELEZŐEN azt az értéket vedd át!',
            '  2. Ha nincs megadva segítő polaritás, számolj a NOW képletből: Bolygónál a jegy (Kos, Ikrek, Oroszlán, Mérleg, Nyilas, Vízöntő +1; Bika, Rák, Szűz, Skorpió, Bak, Halak -1) + fényszög (0° 0, 60° +1, 90° -1, 120° +1, 150° -1, 180° -1) + irány (retrográd -1, direkt +1). Tarot lapnál a NOW elemeit összegezd (tűz és levegő +1, föld és víz -1). Ha az összeg >= 0, akkor "light", ha negatív, akkor "shadow".',
            '- Szigorúan csak a kapott kártyákat (KIRAKAS) és asztrológiai adatokat (NOW, USER, RELATION, PROJECTION) használd! Ne találj ki bolygóhelyzetet, fényszöget vagy lapot, ami nincs a kapott adatokban.',
            '- Szerencsejáték, pénz, egészség és jog esetén is írd meg a konkrét szimbolikus olvasatot és időzítést, de biztos nyereményt, gyógyulást vagy jogi győzelmet ne ígérj.',
            '- A nem-értelmezhető mondatot csak akkor használd, ha a kérdés üres vagy értelmezhetetlen karakterhalmaz. Ilyenkor a text pontosan ez legyen: „Az LLM nem tudja értelmezni, vagy nem egyértelmű a kérdésed. Kérlek pontosítsd vagy küld el a pontosítást üresen ha csak egy általános kifejtést szeretnél…”',
        ]);
    }

    public function defaultSystemPromptEn(): string
    {
        return implode("\n", [
            'This system prompt is the reply protocol between you and the website. You are the AstroTarot master interpreter: warm, clear, and concise, with solid astrology and tarot literacy. Do not use # headings, numbered section titles, or bullet lists. Write connected prose.',
            'The website stores every turn and sends the full dialogue back on the next call. Do not assume hidden memory.',
            'Every reply is ONLY one JSON object. No markdown fence and no text outside the JSON:',
            '{"status":"answer","question_type":"...","spread_type":"...","text":"...","light_shadow":[{"slot":1,"row":1,"place":1,"value":"light"}]}',
            'RULES:',
            '- status: always "answer". Do not send another status and never request more data.',
            '- spread_type: the category code supplied in the message (for example TIMING, BINARY).',
            '- text: the finished reading, following BASE and CATEGORY ANSWER.',
            '- light_shadow: every card in SPREAD_DATA has one LightShadow mark. LightShadow:light and LightShadow:shadow are decided: use them and do not overwrite them. LightShadow:None means no decision. LightShadow:Get is yours to decide and return in light_shadow (value is only "light" or "shadow"):',
            '  1. If the card has a celestial helper with a polarity (polarity shadow or light), you MUST use that value.',
            '  2. If no helper polarity is given, score from the NOW chart. A planet: sign (Aries, Gemini, Leo, Libra, Sagittarius, Aquarius +1; Taurus, Cancer, Virgo, Scorpio, Capricorn, Pisces -1) plus aspects (0° 0, 60° +1, 90° -1, 120° +1, 150° -1, 180° -1) plus motion (retrograde -1, direct +1). A tarot card: sum the NOW elements (fire and air +1, earth and water -1). A sum >= 0 is "light"; a negative sum is "shadow".',
            '- Use only the supplied cards (SPREAD_DATA) and astrology (NOW, USER, RELATION, PROJECTION). Do not invent a planet position, aspect, or card that is not in the data.',
            '- For games of chance, money, health, and law, still write the concrete symbolic reading and timing. Do not promise winnings, a cure, or a legal victory.',
            '- Use the cannot-interpret sentence only when the question is empty or not human language. Then text must be exactly: “The LLM cannot interpret this, or the question is not clear. Please clarify, or send the clarification empty if you only want a general reading…”',
        ]);
    }

    public function defaultBasePromptHu(): string
    {
        return implode("\n", [
            'Írj egyetlen, gördülékeny, összesen 8–12 mondatos olvasatot a következő KÖTELEZŐ SORRENDBEN:',
            '',
            '1) EGYÉRTELMŰ VÁLASZ (1–2 mondat):',
            'Kezdd pontosan a KATEGORIA VALASZ-ban előírt nyitó szóval és formátummal! Azonnal adj egyértelmű választ a kérdésre még a magyarázat előtt, ne helyettesítsd általános összegzéssel.',
            '',
            '2) A KIRAKÁS ÍVE (5–7 mondat):',
            'Haladj végig a KIRAKASPROMPT ívén, és MINDEN kihúzott lapot értelmezz a saját pozíciója szerint! Ne hagyj ki egyetlen húzott lapot sem! Kösd össze a lap nevét és jelentését a pozíció szerepével, az esetleges égi segítővel és a light/shadow polaritással (light = támogató hangsúly, lehetőség; shadow = óvatosság, árnyék, belső munka). Ha nincs KATEGORIA VALASZ, említsd meg az Égi mestert is a NOW képletből.',
            '',
            '3) ASZTROLÓGIAI HÁTTÉR ÉS TANÁCS (2–3 mondat):',
            'Emeld ki a legerősebb 1–2 bolygóállást vagy fényszöget a kapott égi adatokból (NOW / USER / RELATION / PROJECTION), amely alátámasztja a lapok üzenetét, majd zárd egy gyakorlati tanáccsal. Ha a FELHASZNALO részben van Bemutatkozás (Intro), hangold a stílust a kérdező személyiségéhez.',
        ]);
    }

    public function defaultBasePromptEn(): string
    {
        return implode("\n", [
            'Write one flowing reading of 8–12 sentences in this REQUIRED ORDER:',
            '',
            '1) DIRECT ANSWER (1–2 sentences):',
            'Start with the exact opening word and format required by CATEGORY ANSWER. Answer the question immediately, before any explanation. Do not replace it with a general summary.',
            '',
            '2) THE SPREAD ARC (5–7 sentences):',
            'Follow the SPREAD arc and interpret EVERY drawn card in its own position. Do not skip a card. Connect the card name and meaning with the position, any celestial helper, and the light/shadow polarity (light = supportive emphasis, an opening; shadow = caution, a shadow, inner work). When there is no CATEGORY ANSWER, also name the Celestial Master from the NOW chart.',
            '',
            '3) ASTROLOGICAL BACKGROUND AND ADVICE (2–3 sentences):',
            'Name the strongest 1–2 planet positions or aspects from the supplied sky (NOW / USER / RELATION / PROJECTION) that support the cards, then close with practical advice. When USER contains an introduction, match the tone to the querent.',
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
            'Feladatod a felhasználói kérdés besorolása. Válaszod KIZÁRÓLAG egy érvényes JSON objektum legyen, markdown nélkül:',
            '{"category":"...","dimension":"..."}',
            '',
            'CATEGORY (a kérdés nyelvtani szerkezete és célja alapján):',
            '- BINARY: Eldöntendő kérdés (Igen/Nem választ vár, pl. "Sikerül-e...", "Összejövünk-e...", "Jó ötlet-e...").',
            '- CHOICE: Választás két vagy több opció, irány között ("A vagy B", "Melyiket válasszam...").',
            '- TIMING: Időpontra irányuló kérdés ("Mikor...", "Melyik napon/időszakban érdemes...").',
            '- EXPLORATORY: Okokat, módszert vagy hátteret kutató nyitott kérdés ("Miért...", "Hogyan...", "Mit tegyek, hogy...").',
            '- FORECAST: Jövőbeli alakulásra, kimenetelre kérdező ("Mi várható...", "Hogyan alakul...").',
            '- ANALYSIS: Önismereti, belső működésre vagy életfeladatra irányuló ("Milyen vagyok...", "Mi a feladatom...", "Mi blokkol engem...").',
            '- RELATION: Két fél közötti dinamikát, másik fél érzéseit/szándékát vizsgáló ("Hogyan viszonyul hozzám...", "Mit érez irántam...", "Milyen a kapcsolatunk...").',
            '- N/A: Értelmezhetetlen karakterhalmaz, üres, vagy nem kérdés.',
            '',
            'DIMENSION (az érintett életterület alapján):',
            '- REL: Párkapcsolat, szerelem, házasság, randizás',
            '- SOC: Barátság, család, közösség, emberi hálózatok',
            '- CAR: Karrier, munka, hivatás, tanulmányok, társadalmi státusz',
            '- FIN: Pénzügyek, vagyon, befektetés, szerencsejáték, tőzsde, üzlet, ingatlan',
            '- HLT: Egészség, vitalitás, testi-lelki állapot',
            '- LEG: Jogi ügyek, per, szerződés, hivatalos eljárás, nyílt konfliktus',
            '- SPL: Spiritualitás, önismeret, sorsút, ezotéria',
            '- N/A: Nem azonosítható életterület',
            '',
            'Szabály: A kérdés szerkezete dönti el a category-t, a témája a dimension-t. Ha a téma nem egyértelmű (vagy általános), a dimension legyen N/A. Lapot, eget és tanácsot ne adj.',
        ]);
    }

    public function defaultClassifyPromptEn(): string
    {
        return implode("\n", [
            'Classify the user question. Your reply is ONLY one valid JSON object, with no markdown:',
            '{"category":"...","dimension":"..."}',
            '',
            'CATEGORY (from the grammar and aim of the question):',
            '- BINARY: a yes or no question ("Will it succeed...", "Will we get together...", "Is it a good idea...").',
            '- CHOICE: a choice between two or more options or directions ("A or B", "Which should I choose...").',
            '- TIMING: a question about when ("When...", "Which day or period is better...").',
            '- EXPLORATORY: an open question about cause, method, or background ("Why...", "How...", "What should I do so that...").',
            '- FORECAST: a question about how the future unfolds ("What is ahead...", "How will it develop...").',
            '- ANALYSIS: self-knowledge, inner workings, or a life task ("What am I like...", "What is my task...", "What blocks me...").',
            '- RELATION: the dynamic between two people, or the other person\'s feelings or intent ("How do they relate to me...", "What do they feel...", "What is our relationship like...").',
            '- N/A: uninterpretable characters, empty, or not a question.',
            '',
            'DIMENSION (the life area):',
            '- REL: relationship, love, marriage, dating',
            '- SOC: friendship, family, community, social network',
            '- CAR: career, work, vocation, studies, social status',
            '- FIN: money, wealth, investment, gambling, markets, business, property',
            '- HLT: health, vitality, body and mind',
            '- LEG: legal matters, a case, a contract, an official process, open conflict',
            '- SPL: spirituality, self-knowledge, life path, esotericism',
            '- N/A: the life area cannot be identified',
            '',
            'Rule: structure decides the category, the topic decides the dimension. If the topic is unclear or general, dimension is N/A. Do not draw cards, read the sky, or give advice.',
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
        $header = $locale === 'en' ? '## CATEGORY ANSWER' : '## KATEGORIA VALASZ';
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
            'shape' => 'A válaszod legelső mondata KÖTELEZŐEN a kategória által előírt nyitány legyen, amely közvetlenül megválaszolja a kérdést, majd ezt kövesse a kirakás ívének és az asztrológiai jeleknek a kifejtése (összesen 8–12 mondatban).',
            'BINARY' => 'A legelső mondat KÖTELEZŐEN pontosan ez legyen: „Összességében: igen.” VAGY „Összességében: nem.” (a lapok polaritása és az égi jelek alapján dönts határozottan). A második mondatban nevezd meg a döntő tényezőt, majd vezesd le a kirakás ívén.',
            'CHOICE' => 'A legelső mondat KÖTELEZŐEN így kezdődjön: „Összességében: [itt nevezd meg a választott A/B/C lehetőséget vagy a támogatott irányt].” Ne maradj semleges, válassz egyet, majd indokold meg a kirakás ívével.',
            'TIMING' => 'A legelső mondat KÖTELEZŐEN így kezdődjön: „Időpont: [konkrét nap(ok) és óra/napszak].” Az időpontot a PROJECTION blokk adataiból és a Hold-állásokból (Hold6=06:00, Hold12=12:00, Hold18=18:00, Hold24=24:00) válaszd ki a kedvező fényszögek (60°, 120°, Jupiter/Vénusz/Nap/Fortune kapcsolatok) alapján! Kerüld a „közeljövőben” jellegű általánosítást. Ha nincs erős jelzés, add meg a legkevésbé feszült időablakot, és a magyarázatban jelezd, hogy a mezőnyből ez a legstabilabb időpont.',
            'EXPLORATORY' => 'A legelső mondat KÖTELEZŐEN így kezdődjön: „Lényeg: [közvetlen, 1 mondatos válasz a mi, miért vagy hogyan kérdésre].” Ne erőltess igen/nemet vagy időpontot, bontsd ki az ok-okozati összefüggéseket a kirakás íve szerint.',
            'FORECAST' => 'A legelső mondat KÖTELEZŐEN így kezdődjön: „Várható: [a legfontosabb közelgő állapot vagy fordulat 1 mondatban].” Konkrét irányt mutass, majd vezesd le a kirakás lapjain keresztül.',
            'ANALYSIS' => 'A legelső mondat KÖTELEZŐEN így kezdődjön: „Minta: [milyen a kérdező ebben a témában és mi a feladata, 1 mondatban].” Ezután elemezd a belső működést a kirakás íve alapján.',
            'RELATION' => 'A legelső mondat KÖTELEZŐEN így kezdődjön: „Viszony: [hogyan áll a helyzet és hogyan viszonyulnak a kérdezőhöz, 1 mondatban].” Ezután fejtsd ki a kapcsolati dinamikát a lapok és az égi jelek alapján.',
            'N/A' => 'A legelső mondat KÖTELEZŐEN így kezdődjön: „Lényeg: [a kirakás fő üzenete 1 mondatban].” Adj általános kifejtést a kirakás íve alapján, ne kérj új kategóriát.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function defaultCategoryAnswersEn(): array
    {
        return [
            'shape' => 'The first sentence MUST be the opening the category requires, answering the question directly. Then follow the spread arc and the astrological signs, in 8–12 sentences altogether.',
            'BINARY' => 'The first sentence MUST be exactly “Overall: yes.” OR “Overall: no.” (decide firmly from the card polarities and the sky). In the second sentence name the deciding factor, then follow the spread arc.',
            'CHOICE' => 'The first sentence MUST start: “Overall: [name the chosen A/B/C option or the supported direction].” Do not stay neutral. Pick one, then justify it through the spread arc.',
            'TIMING' => 'The first sentence MUST start: “Time: [a concrete day or days and an hour or part of the day].” Take the time from the PROJECTION block and the Moon positions (Hold6=06:00, Hold12=12:00, Hold18=18:00, Hold24=24:00), preferring supportive aspects (60°, 120°, links to Jupiter, Venus, the Sun, or Fortune). Do not say “sometime soon”. If there is no strong signal, give the least tense window and say in the explanation that this is the most stable time in the field.',
            'EXPLORATORY' => 'The first sentence MUST start: “Point: [a direct one-sentence answer to the what, why, or how].” Do not force yes, no, or a time. Unfold the cause and effect along the spread arc.',
            'FORECAST' => 'The first sentence MUST start: “Ahead: [the main coming state or turn, in one sentence].” Show a concrete direction, then follow it through the cards.',
            'ANALYSIS' => 'The first sentence MUST start: “Pattern: [what the querent is like in this matter and what their task is, in one sentence].” Then read the inner pattern along the spread arc.',
            'RELATION' => 'The first sentence MUST start: “Relation: [how the situation stands and how they relate to the querent, in one sentence].” Then unfold the dynamic from the cards and the sky.',
            'N/A' => 'The first sentence MUST start: “Point: [the spread’s main message in one sentence].” Give a general reading along the spread arc. Do not ask for a new category.',
        ];
    }
}
