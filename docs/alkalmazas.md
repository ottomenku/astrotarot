# AstroTarot — ami a gyári Laraveltől eltér

Ez a leírás a `D:\tarot` Laravel 12 alkalmazás saját részeit foglalja össze: csomagok, konfiguráció, feltöltött fájlok, majd a modellek, szolgáltatások, vezérlők, nézetek és útvonalak. Minden tételnél ott van, mit csinál, és hol használja a program.

A felület nyelve magyar és angol (`lang/hu`, `lang/en`). A felhasználói oldal nem Vite-alkalmazás: a kinézet a `public/templates/art-deco` mappában van.

Éles másolat: `https://astrotarot.hu`, a szerveren `/var/www/tarot`. A kód a `master` ágon van: `https://github.com/ottomenku/astrotarot`.

## Gyári vázhoz képest

A Laravel 12 váz megvan (`artisan`, `bootstrap/app.php`, `routes/web.php`, gyári `users` / `cache` / `jobs` / `sessions` táblák). Erre épül:

- saját webes útvonalak, middleware-ek és JSON hibaválasz a `bootstrap/app.php`-ban
- a `settings` tábla és az `AppSettings` szolgáltatás a szerkeszthető szövegekhez és kapcsolókhoz
- tarot-húzás, többfordulós LLM-olvasás, efemerisz (Python + Swiss Ephemeris)
- paklik, kirakások, jelentéscsoportok, kreditek, csomagok, blog, jogi szövegek
- az art deco sablon statikus CSS/JS-sel

Nincs saját API-route fájl (`routes/api.php`). A parancsok a `routes/console.php` fájlban üresek; a két saját Artisan-parancs az `app/Console/Commands` mappából töltődik.

## Telepített csomagok

### PHP (`composer.json`)

| Csomag | Szerep | Hol használja |
| --- | --- | --- |
| `laravel/framework` ^12 | Az alkalmazás váza | mindenhol |
| `laravel/socialite` ^5 | Google és Facebook belépés | `SocialAuthController`, `config/services.php` |
| `laravel/tinker` | Konzolos kipróbálás | fejlesztés |
| `dompdf/dompdf` ^3 | PDF az olvasatból | `ReadingExportService::makePdf()` |

Fejlesztői csomagok, élesen nem kellenek: `phpunit/phpunit`, `mockery/mockery`, `fakerphp/faker`, `laravel/pint`, `laravel/pail`, `laravel/sail`, `nunomaduro/collision`.

### Python

| Csomag / fájl | Szerep | Hol használja |
| --- | --- | --- |
| `pyswisseph` (`requirements.txt`, a `.venv` környezetben) | Bolygók, házak, állócsillagok | `python/ephemeris_calc.py` |
| `python/ephe/sefstars.txt` | Állócsillag-katalógus | ugyanaz a script |

A PHP a `EphemerisService`-ből indítja a scriptet. Windowsos bináris: `.venv/Scripts/python.exe` (`EPHEMERIS_PYTHON_BIN`).

## Konfiguráció

### Saját config

`config/ephemeris.php`

- `python_bin` — a venv Pythonja
- `script` — `python/ephemeris_calc.py`
- `timeout` — 30 másodperc
- `default_lat` / `default_lon` — Budapest (47.4979, 19.0402). A házszámítás ezt használja, születési helyet a program nem kér.
- `conjunction_orb` — 1 fok

Ezt olvassa: `EphemerisService`, `EphemerisChartContext`.

`config/services.php` saját blokkjai:

- `openai` — driver (`openai` vagy `ollama`), kulcs, URL, modell, timeout, tartalék OpenAI, kis és nagy Ollama-modell. Olvassa: `OpenAIClient`, az admin Tesztmód.
- `google`, `facebook` — Socialite kliensazonosítók. Olvassa: `SocialAuthController`.

`config/app.php`

- `contact_email` a `CONTACT_EMAIL` változóból. Olvassa a kapcsolat oldal.
- időzóna: `UTC`. A születési idő a felületen budapesti helyi idő, tároláskor UTC.
- alapnyelv a `.env` szerint magyar (`APP_LOCALE=hu`).

### Környezeti változók (`.env.example`)

A gyári Laravel-kulcsokon felül:

| Változó | Jelentés |
| --- | --- |
| `DB_*` | MySQL, adatbázis neve `tarot` |
| `SESSION_DRIVER=database` | munkamenet a `sessions` táblában |
| `CACHE_STORE=database` | beállítások és az efemerisz „most / rögzített idő” cache-e |
| `QUEUE_CONNECTION=database` | a `jobs` tábla megvan; az alkalmazás nem sorba tett feladatot indít |
| `CONTACT_EMAIL` | kapcsolat oldal |
| `EPHEMERIS_PYTHON_BIN` | Swiss Ephemeris Python |
| `LLM_DRIVER`, `OPENAI_*`, `OLLAMA_*`, `LLM_FALLBACK_OPENAI` | olvasás modellje |
| `GOOGLE_*`, `FACEBOOK_*` | közösségi belépés |

Az admin Tesztmód a `settings` táblában felülírhatja, hogy a helyi Ollama (kis vagy nagy modell) vagy csak az OpenAI menjen.

## Feltöltött és statikus fájlok

Ezek nem a `vendor` és nem a fordított Blade-cache. A böngésző és a paklitelpítő innen olvas.

| Hely | Tartalom | Ki használja |
| --- | --- | --- |
| `public/templates/art-deco/app.js`, `app.css` | húzás, olvasás, menü, fiók űrlapok stílusa | `templates/art-deco/home.blade.php` |
| `public/templates/art-deco/landing.css`, `landing-tarot.jpg` | vendég nyitóoldal | `templates/art-deco/landing.blade.php` |
| `public/templates/art-deco/auth-modal.js` | belépés / regisztráció ablak a nyitón és a login oldalon | `auth-modal.blade.php`, `auth/login.blade.php` |
| `public/card-pack/<slug>/` | egy pakli: `deck.json`, `figures/`, gyakran `card-back/` | `CardPackCatalog`, `PackInstallService`, `PackCardImporter`, `OgImageService`, a húzás JS-e |
| `public/card-pack` paklijai | `tarot`, `egi-orakulum`, `egi-orakulum-ls`, `cigany-kartya`, `anime-tarot`, `anime-orakulum`, `cosplay-tarot`, `cosplay-orakulum`, `tacsko-tarot`, `tacsko-orakulum` | adatbázis `card_packs` sorai és a kirakás szabályai |
| `public/media/blog-covers/` | blog borítók (a mappa gitignore-olja a képeket) | `BlogPost::coverUrl()`, admin blog űrlap |
| `public/og/` | promóciós Open Graph kép, ha az `og:promo-image` parancs legenerálta | megosztás, blog, ha nincs saját borító |
| `deploy/tarot.conf` | Apache virtual host az `astrotarot.hu` névhez, document root `public/` | a szerver webszervere |
| `deploy/*.sh`, `deploy/*.php`, `deploy/ollama-vps-tunnel.ps1` | éles frissítés, levél, OAuth, prompt-visszaállítás, tartalomszinkron, Ollama-alagút | kézi futtatás a gépen vagy a VPS-en |

A pakli képeit az admin „Kártya telepítés” (`PackInstallController` → `PackInstallService`) zipből vagy mappából teszi a `public/card-pack` alá, és közben `card_packs` / `pack_cards` sort ír.

## Middleware

Mind a `bootstrap/app.php`-ban van bekötve. A web csoport minden oldalra lefut, ebben a sorrendben elölről:

| Osztály | Mit csinál | Hol számít |
| --- | --- | --- |
| `RedirectToCanonicalHost` | Biztonságos kérésnél a `APP_URL` hostjára irányít (www és cloud → `astrotarot.hu`). A localhostot békén hagyja. | minden GET |
| `SetLocale` | `hu` vagy `en` a sessionből | minden oldal, a `/locale/{locale}` írja |
| `EnsureUserNotBanned` | kitiltott felhasználót kijelentkeztet | belépett, `banned_at` |
| `EnsureSiteOpen` | ha a site ki van kapcsolva, vendég a karbantartó oldalt látja; a login és az admin megmarad | `AppSettings::siteEnabled()`, `maintenance.blade.php` |
| `RestrictGuestsWhenRegistrationClosed` | zárt regisztrációnál a vendég a nyitón marad | `AppSettings::registrationEnabled()` |

Aliasok, csak a megjelölt útvonalon:

| Alias | Osztály | Mit csinál |
| --- | --- | --- |
| `admin` | `EnsureAdmin` | csak `Role::Admin` |
| `paid` | `EnsurePaidFeatures` | ha a fizetős funkciók ki vannak kapcsolva, a kreditoldalak nem nyílnak |
| `feature` | `EnsureFeatureAccess` | egy `FeatureCatalog` kulcsra megkérdezi a `FeatureAccessService`-t (csomag, kredit, admin kivétel) |

A JSON-válaszoknál a 419-es lejárt munkamenet és az üres `error` mező a `bootstrap/app.php` kivételkezelőjében kap szöveget. A húzás JS-e ezt a `error` mezőt mutatja.

## Modellek

A táblanév a Laravel többesszáma, kivéve a `FeatureAccess` (`feature_access`).

| Modell | Tábla | Mit tárol | Ki használja |
| --- | --- | --- | --- |
| `User` | `users` | fiók: szerep, kredit, csomag (`plan`), születés UTC-ben (`birth_at`), nem, bemutatkozás (max. 200), közösségi provider, sablon, ÁSZF/adatkezelés ideje, kitiltás, napi és havi kredit időbélyeg | auth, `CreditService`, `AccountController`, `TarotReadingService` (születés, nem, bemutatkozás a felhasználói blokkban), `PersonalDataService` |
| `Template` | `templates` | választható kinézet (`slug`, aktív jelző). Most az `art-deco` él. | `HomeController`, `TemplateController`, `User::template()` |
| `Setting` | `settings` | kulcs–érték: promptok, jogi szöveg, site/regisztráció/LLM/fizetős kapcsolók, kérdés minimális hossza | `AppSettings` |
| `Game` | `games` | kirakás: nevek, leírás, ikon, kérdés kell-e, kirakásprompt (`prompt_hu` / `prompt_en`), light-shadow mód, alapértelmezett és aktív | `GameController`, `GameConfigService`, `HomeController` |
| `GameRule` | `game_rules` | a kirakás egy helye: pakli, hátlap, cím, leírás, sor, hely, húzási sorrend, infó mód (`astro`, `meaning`, `none`) | `Game`, `GameConfigService`, `ReadingController::cardsWithGameRules()` |
| `CardPack` | `card_packs` | pakli vagy hátlap: típus `tarot` / `oracle` / `card_back`, slug, nevek, jelentéscsoport, előnézet, aktív, alapértelmezett | pakliadmin, `PackResolutionService`, `CardPackCatalog` |
| `PackCard` | `pack_cards` | egy lap a pakliban: ábra azonosító, kép, jelentéscsoport-tétel | `PackCardImporter`, `PackInstallService`, `CardPackController` |
| `UserPackPreference` | `user_pack_preferences` | a felhasználó választott figura-paklija és hátlapja | `PackPreferenceController`, `PackResolutionService` |
| `UserSpreadPreference` | `user_spread_preferences` | utoljára választott kirakás, paklik és hátlap | `HomeController` a `?spread=1` mentéskor |
| `MeaningGroup` | `meaning_groups` | lapjelentések csoportja (nagy arkánum, kis arkánum, cigány, saját) | `MeaningGroupController`, `FigureMeaningService` |
| `MeaningEntry` | `meaning_entries` | egy lap neve és jelentése a csoporton belül, hu/en | jelentésadmin, `FigureMeaningService` |
| `FigureMeaning` | `figure_meanings` | gyorsítótár: pakli + lap + nyelv szövege, forrás `deck` vagy `llm` | `FigureMeaningService` |
| `ReadingSession` | `reading_sessions` | egy olvasás folyamata: `public_id`, tulajdonos, kirakás, kérdés, lapok | `TarotReadingService`, `ReadingController` |
| `ReadingTurn` | `reading_turns` | a session egy fordulója (szerep, fajta, szöveg). A következő hívás az egészet újra elküldi. | `TarotReadingService` |
| `ReadingLog` | `reading_logs` | lezárt kérdés–válasz napló, modell és forrás címke | történet, fiók, admin értelmezések, `PersonalDataService` |
| `ReadingShare` | `reading_shares` | nyilvános megosztó token és a lapok pillanatképe | `ReadingExportController`, `ReadingExportService` |
| `Package` | `packages` | kreditcsomag: ár HUF/USD, kredit, periódus, ikon, perk kulcsok, leírás, aktív | `PackageController`, `CreditService`, `FeatureAccess` pivot |
| `FeatureAccess` | `feature_access` | egy funkció ára kreditben, ÉS/VAGY logika, felirat. A csomagok a `feature_access_package` pivoton. | `FeatureAccessService`, `EnsureFeatureAccess`, csomagűrlap `syncFeatureAccess` |
| `CreditRequest` | `credit_requests` | „kérem ezt a csomagot” igény, fizetés nélkül | `CreditPackageController`, fiók oldal |
| `DataDeletionRequest` | `data_deletion_requests` | törlési kérelem, állapot, feldolgozó admin | `AccountController`, `DataDeletionRequestController`, `PersonalDataService` |
| `BlogPost` | `blog_posts` | cikk, slug, kivonat, borító, közzététel | `BlogController`, `BlogPostController` |
| `BlogComment` | `blog_comments` | hozzászólás | `BlogCommentController` |
| `BlogLike` | `blog_likes` | like, egy felhasználó egy cikken | `BlogLikeController` |

`Role` enum (`user`, `pro`, `expert`, `admin`): a felhasználó szerepe. Az admin minden funkciót megkap. A csomag (`plan`) ettől külön mező, a `FeatureAccess` szabályai ehhez igazodnak.

A `Game` light-shadow módja (`full-astro`, `oracle-astro`, `full-random`) megmondja, mely lapok polaritását számolja az égbolt, és melyiket sorsolja véletlen. A húzás JS-e a `gameConfig.light_shadow_mode` mezőt olvassa.

## Szolgáltatások (`app/Services`)

| Osztály | Mit csinál | Ki hívja |
| --- | --- | --- |
| `AppSettings` | `settings` olvasás/írás, cache `app.settings.bag`. Rendszerprompt, alapprompt, jogi szöveg, site, regisztráció, LLM-útvonal, fizetős funkciók, kérdés hossza. | admin beállítások, prompt, jogi oldal, middleware, olvasás |
| `TarotReadingService` | egy olvasás fordulói. Nyitó üzenet: alapprompt, kirakásprompt, kirakás adatai, felhasználó (kérdés, születés, nem, bemutatkozás). Legfeljebb 3 égi adatkérés, utána válasz. Tisztázás és bővítés ugyanannak a sessionnek a következő fordulója. | `ReadingController` |
| `ReadingReplyParser` | a modell JSON-jából `need_data` vagy `answer`, legfeljebb 4 kérés | `TarotReadingService` |
| `ReadingSkyLookup` | a kért bolygó-, jegy-, fényszög-, állócsillag-, ház- és vetületadat. Születés hiányában a születési kérés hibát ad, a modell nem találhat ki eget. | `TarotReadingService` |
| `EphemerisService` | elindítja a Pythont: `calculate()` pillanatkép, `project()` időablak (fényszög, ház, retrográd) | `ReadingSkyLookup`, `CardDrawProfileService`, admin efemerisz |
| `EphemerisChartContext` | a húzáshoz „most” vagy az admin által rögzített idő, cache-ben | admin efemerisz, `CardDrawProfileService` |
| `CardDrawProfileService` | a mai ég domináns eleme, minősége, hangulata a húzás polaritásához. A kész profilt percenként röviden cache-eli. | `HomeController` |
| `AstroScoringEngine` | elem, minőség, pozitív/negatív pont a bolygóállásból | `EphemerisService::enrichWithScoring()` |
| `TarotPositionPolarity` | a kirakás helyeinek light/shadow besorolása a pontszámokból | `EphemerisService`, a húzás profilja |
| `OpenAIClient` | chat hívás OpenAI vagy Ollama felé, tartalékkal | `TarotReadingService`, `reading:check` |
| `LlmChatResult` | a chat válaszának burkolója (szöveg, modell, honnan jött) | `OpenAIClient` |
| `GameConfigService` | a kiválasztott kirakás JSON-ja a főoldalnak: helyek, paklik, hátlap, light-shadow mód | `HomeController` |
| `PackResolutionService` | játék alap paklija, prémium felülírás, hátlap, engedélyezett deck | `GameConfigService`, `ReadingController`, `PackPreferenceController` |
| `CardPackCatalog` | slug aliasok (`oracle` → `egi-orakulum`), fájlútvonalak a `public/card-pack` alatt | paklik, OG-kép, húzás |
| `PackInstallService` | zip telepítése paklivá | `PackInstallController` |
| `PackCardImporter` | meglévő mappából `pack_cards` sorok, ha még nincsenek | telepítés, `deploy/sync-local-content.php` |
| `FigureMeaningService` | lapjelentés: előbb a jelentéscsoport, aztán a `figure_meanings` cache, kellő esetben LLM | `TarotReadingService` a lapok szövegéhez |
| `FeatureAccessService` | egy funkció engedélyezett-e, kredit, csomag, ÉS/VAGY. Üres csomaglista: nincs csomagkorlát. Admin átmegy. A fizetős kapcsoló kikapcsolva mindent kinyit. | middleware, főoldal ikonok, olvasás, export |
| `CreditService` | regisztrációs bónusz, napi bónusz, admin havi keret, csomagok, költés, promóciós csomag | regisztráció, login, kreditoldal, olvasás, export |
| `ReadingExportService` | HTML, PDF, megosztó rekord, kártyakép a levélhez | `ReadingExportController` |
| `OgImageService` | lapkép és kétlapos JPEG a Facebook-előnézethez (GD) | export, `og:promo-image` |
| `PersonalDataService` | beszélgetések törlése, anonimizálás, törlési kérelem teljesítése | fiók, admin törlési kérelmek |

`app/Support` segédek, nem HTTP:

| Osztály | Mit csinál | Ki használja |
| --- | --- | --- |
| `FeatureCatalog` | a funkciókulcsok és hogy vonnak-e kreditet | middleware, szolgáltatások, Blade `feature-marks` |
| `PackageBody`, `PackageIcons` | csomagkártya szövege és ikonja a perk kulcsokból | `PackageController`, `FeatureAccessService` |
| `AdminIcons` | admin szerepikon | `Role::iconSvg()`, felhasználólista |
| `HtmlSanitizer` | a jogi és blog HTML tisztítása | admin jogi oldal, blog |
| `ZipBundle` | zip olvasása a paklitelpítéshez | `PackInstallService` |
| `MajorArcanaMeaningCatalog`, `MinorArcanaMeaningCatalog`, `GypsyCardMeaningCatalog`, `BaseMeaningCatalog` | a migrációk ezekből töltik a jelentéscsoportokat | `database/migrations` seedjei |

`app/Mail/ReadingExportMail`: az olvasat e-mailje, a `exports/reading-mail` nézettel. Küldi: `ReadingExportController`.

## Vezérlők

### Nyilvános

| Vezérlő | Mit csinál | Nézet / hívó |
| --- | --- | --- |
| `HomeController` | Nyitó. Vendégnek landing, `?draw=1` vagy belépés után a húzó oldal. `?spread=1` elmenti a kirakásválasztást. | `templates/art-deco/landing`, `home`, tartalék `welcome` |
| `LocaleController` | nyelv váltása | redirect vissza |
| `PromoShareController` | a bevezető megosztó oldal | `exports/promo-share` |
| `LegalPageController` | ÁSZF, adatkezelés, kapcsolat | `legal/show`, `legal/contact` |
| `BlogController` | cikklista és cikk | `blog/index`, `blog/show` |
| `ReadingController` | `POST /reading`: első olvasás, tisztázás, bővítés. Validál, jogosultságot és kreditet néz, sessiont visz. | JSON a `app.js` `requestReading` függvényének |
| `ReadingExportController` | PDF, e-mail, megosztó link, nyilvános `/share/{token}` és OG kép | `exports/reading`, `exports/share` |

### Belépés

| Vezérlő | Mit csinál | Nézet |
| --- | --- | --- |
| `LoginController` | login, logout, napi kredit az adminnak belépéskor | `auth/login` és a modal |
| `RegisterController` | regisztráció, 10 kredit bónusz | modal / `register` POST |
| `LegalAcceptController` | közösségi regisztrációnál ÁSZF elfogadás | `auth/legal-accept` |
| `SocialAuthController` | Google és Facebook redirect + callback | Socialite |

### Belépett felhasználó

| Vezérlő | Mit csinál | Nézet |
| --- | --- | --- |
| `CreditPackageController` | csomagok, igény rögzítése, promóció aktiválása | `credits/packages` |
| `AccountController` | adatok, egy olvasat, olvasatok törlése, jelszó, bemutatkozás, törlési kérelem | `account/show`, `account/reading`, `account/introduction` |
| `PackPreferenceController` | választott pakli és hátlap mentése | az account űrlap POST-ja |
| `ReadingHistoryController` | korábbi olvasatok, ha a history funkció nyitva | `credits/history`, `credits/history-show` |
| `BlogLikeController`, `BlogCommentController` | like és hozzászólás | a blog cikkoldal |

### Admin (`/admin`, `auth` + `admin`)

| Vezérlő | Mit csinál | Nézet |
| --- | --- | --- |
| `EphemerisController` | égbolt tábla, „most” vagy rögzített idő a húzáshoz | `admin/ephemeris/index` |
| `SettingsController` | site, regisztráció, LLM, fizetős funkciók, kérdés hossza | `admin/settings/edit` |
| `PromptController` | rendszerprompt és alapprompt hu/en, visszaállítás a kód alapjára | `admin/prompts/edit` |
| `LegalController` | ÁSZF és adatkezelés hu/en | `admin/legal/edit` |
| `PackageController` | csomagok, perk pipák. A pipa a leírást írja, és ha a funkció már csomaghoz kötött, a pivotot is | `admin/packages/index`, `form` |
| `GameController` | kirakások és helyek | `admin/games/index`, `form`, `partials/rule-row` |
| `CardPackController` | paklik, lapok, alapértelmezett | `admin/packs/index`, `form` |
| `PackInstallController` | zipből pakli | `admin/packs/install` |
| `MeaningGroupController` | jelentéscsoportok, másolás, törlés | `admin/meaning-groups/*` |
| `BlogPostController` | cikkek | `admin/blog/*` |
| `ReadingLogController` | lezárt értelmezések listája, megnyitás, előnézet | `admin/readings/*` |
| `UserController` | felhasználók, kredit jóváírás, kitiltás | `admin/users/*` |
| `DataDeletionRequestController` | törlési kérelmek teljesítése vagy elutasítása | `admin/deletion-requests/index` |
| `TemplateController` | sablon felvétele és aktiválása | `admin/templates/index` |

## Nézetek

A Blade fájlok a `resources/views` alatt. Az admin a `layouts/app` keretet használja, a húzás a saját art deco HTML-t.

| Nézet | Szerep | Ki rendereli |
| --- | --- | --- |
| `layouts/app` | admin keret, menü, villanóüzenet | minden admin oldal |
| `admin/partials/nav` | admin menüpontok | a layout |
| `admin/partials/lang-switch` | hu/en fülek az űrlapokon | prompt, jogi, kirakás, pakli, jelentés, blog, csomag |
| `partials/confirm-submit` | megerősítő kérdés törlés előtt | admin listák |
| `welcome` | tartalék, ha nincs aktív sablon | `HomeController` |
| `maintenance` | zárt site | `EnsureSiteOpen` |
| `templates/art-deco/landing` | vendég nyitó | `HomeController` |
| `templates/art-deco/home` | húzás, kérdés, eredmény, menü. Ide kerül a `gameConfig`, a `drawProfile` és a `featureAccess` JSON. | `HomeController` |
| `templates/art-deco/partials/auth-modal` | belépés és regisztráció | landing, home, login |
| `templates/art-deco/partials/site-footer` | ÁSZF, adatkezelés, kapcsolat | landing és home |
| `auth/login`, `auth/legal-accept` | önálló login és ÁSZF-elfogadás | auth vezérlők |
| `account/show`, `account/reading`, `account/introduction` | fiók | `AccountController` |
| `credits/packages`, `credits/history`, `credits/history-show` | csomagok és történet | kredit és history vezérlők |
| `blog/index`, `blog/show`, `blog/partials/topbar` | blog | `BlogController` |
| `legal/show`, `legal/contact` | jogi oldalak | `LegalPageController` |
| `exports/reading` | PDF HTML | `ReadingExportService` |
| `exports/reading-mail` | e-mail törzs | `ReadingExportMail` |
| `exports/share`, `exports/promo-share` | nyilvános megosztás és promó | export és `PromoShareController` |
| `components/feature-marks` | lakat vagy csomagikon egy funkción | menü, gombok |
| `components/feature-control` | gomb, ami a funkció állapotától függ | a húzó oldal |
| `admin/...` | a fenti admin vezérlők űrlapjai és listái | az admin vezérlők |

A húzás viselkedése a `public/templates/art-deco/app.js`-ben van: lapválasztás, `POST /reading` a `session_id`-val, PDF, e-mail, megosztás.

## Útvonalak

Forrás: `routes/web.php`. A `feature:` middleware a `FeatureCatalog` kulcsát kapja.

### Mindenki

| Metódus és út | Név | Vezérlő |
| --- | --- | --- |
| `GET /` | `home` | `HomeController` |
| `GET /promo` | `promo.share` | `PromoShareController` |
| `GET /locale/{locale}` | `locale.switch` | `LocaleController` |
| `POST /reading` | `reading` | `ReadingController` (throttle 8/perc) |
| `GET /aszf`, `/adatkezeles`, `/kapcsolat` | `legal.terms`, `legal.privacy`, `legal.contact` | `LegalPageController` |
| `GET /blog`, `/blog/{post}` | `blog.index`, `blog.show` | `BlogController` |
| `GET /share/{token}`, `/share/{token}/og.jpg` | `reading.share.show`, `reading.share.og` | `ReadingExportController` |
| `GET /up` | — | Laravel health, a `bootstrap/app.php` adja |

### Vendég

| Út | Név | Vezérlő |
| --- | --- | --- |
| `GET/POST /login` | `login`, `login.store` | `LoginController` |
| `POST /register` | `register` | `RegisterController` |
| `GET/POST /register/legal` | `register.legal`, `register.legal.store` | `LegalAcceptController` |
| `GET /auth/{google\|facebook}/redirect` és `/callback` | `auth.social.redirect`, `auth.social.callback` | `SocialAuthController` |

`POST /logout` (`logout`) belépve, `LoginController`.

### Belépve

| Út | Név | Feltétel |
| --- | --- | --- |
| `GET /credits` | `credits.packages` | `paid` + `credits.packages` |
| `POST /credits/request` | `credits.request` | `paid` + `credits.request` |
| `POST /credits/promo` | `credits.promo` | `paid` + `credits.promo` |
| `GET /account` és az alatta lévő olvasat-, jelszó-, pakli-, bemutatkozás- és törlésútvonalak | `account.*` | `account.show` |
| `GET /my-readings`, `/my-readings/{reading}` | `readings.history`, `readings.history.show` | `readings.history` |
| `POST /reading/export/pdf`, `/email`, `/reading/share` | `reading.export.pdf`, `reading.export.email`, `reading.share.create` | a vezérlő belül nézi a funkciót |
| `POST /blog/{post}/like`, hozzászólás POST/DELETE | `blog.like`, `blog.comments.*` | belépés + throttle |

### Admin

A csoport prefixe `/admin`, neve `admin.*`, middleware `auth` + `admin`. A gyökér átirányít az efemeriszre.

| Út | Név előtag | Vezérlő |
| --- | --- | --- |
| `/admin/ephemeris` | `admin.ephemeris.index` | `EphemerisController` |
| `/admin/settings` | `admin.settings.*` | `SettingsController` |
| `/admin/prompts` | `admin.prompts.*` | `PromptController` |
| `/admin/legal` | `admin.legal.*` | `LegalController` |
| `/admin/packages` | `admin.packages.*` | `PackageController` |
| `/admin/games` | `admin.games.*` | `GameController` |
| `/admin/packs`, `/admin/packs/install` | `admin.packs.*` | `CardPackController`, `PackInstallController` |
| `/admin/meaning-groups` | `admin.meaning-groups.*` | `MeaningGroupController` |
| `/admin/blog` | `admin.blog.*` | `BlogPostController` |
| `/admin/readings` | `admin.readings.*` | `ReadingLogController` |
| `/admin/users` | `admin.users.*` | `UserController` |
| `/admin/deletion-requests` | `admin.deletion-requests.*` | `DataDeletionRequestController` |
| `/admin/templates` | `admin.templates.*` | `TemplateController` |

## Artisan parancsok

| Parancs | Mit csinál |
| --- | --- |
| `reading:check` | kiírja az LLM-útvonalat és hogy az olvasás be van-e kapcsolva. `--ping` egy rövid hívást is küld. |
| `og:promo-image` | két lapból megcsinálja a promóciós Open Graph JPEG-et a `public/og` alá. |

## Éles frissítés

A szerveren, az `otto` felhasználóval, a `/var/www/tarot` mappában a `deploy/deploy-plans.sh` ezt csinálja: `git pull origin master`, `composer install --no-dev`, `php artisan migrate --force`, view/config/route cache ürítés.

A `deploy/sync-local-content.php` a helyi „teszt” kirakást, a cigánykártya jelentéscsoportját és a kódban lévő rendszer- és alappromptot írja az adatbázisba. Ezt külön kell futtatni, a sima pull nem másolja a kézzel felvett kirakást és paklit.
