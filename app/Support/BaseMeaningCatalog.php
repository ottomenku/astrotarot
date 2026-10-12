<?php

namespace App\Support;

use App\Models\CardPack;
use App\Models\MeaningEntry;
use App\Models\MeaningGroup;

final class BaseMeaningCatalog
{
    public const TAROT_SLUG = 'base-tarot';

    public const ORACLE_SLUG = 'base-orakulum';

    /**
     * @return list<array{
     *   figure_id: string,
     *   name_hu: string,
     *   name_en: string,
     *   meaning_hu: string,
     *   meaning_en: string,
     *   description_hu: string,
     *   description_en: string
     * }>
     */
    public static function tarotEntries(): array
    {
        return self::fromDeck(public_path('card-pack/tarot/deck.json'));
    }

    /**
     * @return list<array{
     *   figure_id: string,
     *   name_hu: string,
     *   name_en: string,
     *   meaning_hu: string,
     *   meaning_en: string,
     *   description_hu: string,
     *   description_en: string
     * }>
     */
    public static function oracleEntries(): array
    {
        $entries = self::fromDeck(public_path('card-pack/egi-orakulum/deck.json'));
        $blurbs = self::oracleBlurbs();
        foreach ($entries as $i => $entry) {
            $id = $entry['figure_id'];
            if ($entry['description_hu'] === '' && isset($blurbs[$id])) {
                $entries[$i]['description_hu'] = $blurbs[$id]['hu'];
                $entries[$i]['description_en'] = $blurbs[$id]['en'];
            }
        }

        return $entries;
    }

    public static function seed(): void
    {
        $tarot = self::upsertGroup(
            self::TAROT_SLUG,
            'BaseTarot',
            'Base Tarot',
            'Alap tarot jelentések a klasszikus figurákhoz.',
            'Base tarot meanings for the classic figures.',
            CardPack::TYPE_TAROT,
            self::tarotEntries(),
        );
        $oracle = self::upsertGroup(
            self::ORACLE_SLUG,
            'BaseOrakulum',
            'Base Oracle',
            'Alap orákulum jelentések a bolygókhoz és jegyekhez.',
            'Base oracle meanings for the planets and signs.',
            CardPack::TYPE_ORACLE,
            self::oracleEntries(),
        );

        if ($tarot !== null) {
            CardPack::query()
                ->ofType(CardPack::TYPE_TAROT)
                ->whereIn('slug', ['tarot', 'anime-tarot', 'cosplay-tarot', 'tacsko-tarot'])
                ->whereNull('meaning_group_id')
                ->update(['meaning_group_id' => $tarot->id]);
        }

        if ($oracle !== null) {
            CardPack::query()
                ->ofType(CardPack::TYPE_ORACLE)
                ->whereIn('slug', ['egi-orakulum', 'anime-orakulum', 'cosplay-orakulum', 'tacsko-orakulum'])
                ->whereNull('meaning_group_id')
                ->update(['meaning_group_id' => $oracle->id]);
        }
    }

    /**
     * @param  list<array<string, string>>  $entries
     */
    private static function upsertGroup(
        string $slug,
        string $nameHu,
        string $nameEn,
        string $descriptionHu,
        string $descriptionEn,
        string $type,
        array $entries,
    ): ?MeaningGroup {
        if ($entries === []) {
            return MeaningGroup::query()->where('slug', $slug)->first();
        }

        $group = MeaningGroup::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name_hu' => $nameHu,
                'name_en' => $nameEn,
                'description_hu' => $descriptionHu,
                'description_en' => $descriptionEn,
                'type' => $type,
                'owner_id' => null,
            ],
        );

        foreach ($entries as $index => $entry) {
            MeaningEntry::query()->updateOrCreate(
                [
                    'meaning_group_id' => $group->id,
                    'figure_id' => $entry['figure_id'],
                ],
                [
                    'sort_order' => $index,
                    'name_hu' => $entry['name_hu'],
                    'name_en' => $entry['name_en'],
                    'meaning_hu' => $entry['meaning_hu'],
                    'meaning_en' => $entry['meaning_en'],
                    'description_hu' => $entry['description_hu'],
                    'description_en' => $entry['description_en'],
                ],
            );
        }

        return $group;
    }

    /**
     * @return list<array{
     *   figure_id: string,
     *   name_hu: string,
     *   name_en: string,
     *   meaning_hu: string,
     *   meaning_en: string,
     *   description_hu: string,
     *   description_en: string
     * }>
     */
    private static function fromDeck(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! is_array($decoded['figures'] ?? null)) {
            return [];
        }

        $rows = [];
        foreach ($decoded['figures'] as $figure) {
            if (! is_array($figure)) {
                continue;
            }
            $id = trim((string) ($figure['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $keyword = is_array($figure['keyword'] ?? null) ? $figure['keyword'] : [];
            $blurb = is_array($figure['blurb'] ?? null) ? $figure['blurb'] : [];
            $rows[] = [
                'figure_id' => $id,
                'name_hu' => trim((string) ($figure['hu'] ?? $id)),
                'name_en' => trim((string) ($figure['en'] ?? $id)),
                'meaning_hu' => trim((string) ($keyword['hu'] ?? '')),
                'meaning_en' => trim((string) ($keyword['en'] ?? '')),
                'description_hu' => trim((string) ($blurb['hu'] ?? '')),
                'description_en' => trim((string) ($blurb['en'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array{hu: string, en: string}>
     */
    private static function oracleBlurbs(): array
    {
        return [
            'sun' => [
                'hu' => 'A Nap a lényeget, az éntudatot és a tudatos ragyogást mutatja. A kérdés magja itt válik láthatóvá: ki vagy, és mit akarsz fényre hozni.',
                'en' => 'The Sun shows essence, identity, and conscious radiance. The core of the question becomes visible: who you are, and what you want to bring into the light.',
            ],
            'moon' => [
                'hu' => 'A Hold az érzéseket, a ritmust és a rejtett szükségleteket hozza. Ami most számít, az nem a terv, hanem a hangulat és a test emlékezete.',
                'en' => 'The Moon brings feelings, rhythm, and hidden needs. What matters now is not the plan, but mood and the memory of the body.',
            ],
            'mercury' => [
                'hu' => 'A Merkúr a gondolatot, a beszédet és az összekötést jelenti. A helyzet akkor mozdul, ha tisztán fogalmazol, kérdezel és hallasz.',
                'en' => 'Mercury means thought, speech, and connection. The situation moves when you speak clearly, ask, and listen.',
            ],
            'venus' => [
                'hu' => 'A Vénusz a vonzalmat, az értéket és a békét mutatja. Amit szeretsz vagy amennyire méltónak érzed magad, az formálja a választást.',
                'en' => 'Venus shows attraction, value, and ease. What you love — and how worthy you feel — shapes the choice.',
            ],
            'mars' => [
                'hu' => 'A Mars az akarat, a harc és a cselekvés. Itt nem várakozás kell, hanem irány és bátorság, hogy megvédd, ami a tied.',
                'en' => 'Mars is will, fight, and action. This is not a time to wait, but to take a direction and defend what is yours.',
            ],
            'jupiter' => [
                'hu' => 'A Jupiter a bővülést, a hitet és a szerencsés kaput jelenti. Nyiss nagyobbra: tanulás, utazás vagy egy bőkezű gesztus visz tovább.',
                'en' => 'Jupiter means expansion, faith, and a lucky gate. Open wider: learning, travel, or a generous gesture carries you on.',
            ],
            'saturn' => [
                'hu' => 'A Szaturnusz a határt, az időt és a felelősséget hozza. Ami tartós, az most formát kér: szabály, türelem, érett döntés.',
                'en' => 'Saturn brings boundary, time, and responsibility. What is meant to last now asks for form: a rule, patience, a mature choice.',
            ],
            'uranus' => [
                'hu' => 'Az Uránusz az áttörést, a szabadságot és a hirtelen fordulatot mutatja. Engedd el a sablont, ha a helyzet új szabályt követel.',
                'en' => 'Uranus shows breakthrough, freedom, and a sudden turn. Drop the template if the situation demands a new rule.',
            ],
            'neptune' => [
                'hu' => 'A Neptunusz az álmot, az intuíciót és az oldódást jelenti. Ne erőltesd a kontúrt: hallgass a képre, a zenére, a csendre.',
                'en' => 'Neptune means dream, intuition, and dissolving edges. Do not force the outline: listen to the image, the music, the silence.',
            ],
            'pluto' => [
                'hu' => 'A Plútó az átalakulást, a hatalmat és a mély cserét hozza. Ami elengedhetetlen, az most meghal vagy újjászületik — nincs középút.',
                'en' => 'Pluto brings transformation, power, and a deep exchange. What is essential now dies or is reborn — there is no middle path.',
            ],
            'aries' => [
                'hu' => 'A Kos a kezdés, az első lépés és a tiszta szándék. Ne várd a tökéletes pillanatot: indíts, és a lendület megmutatja az utat.',
                'en' => 'Aries is the start, the first step, and clean intention. Do not wait for the perfect moment: begin, and momentum will show the path.',
            ],
            'taurus' => [
                'hu' => 'A Bika a stabilitást, az értéket és a test biztonságát mutatja. Építs lassan, tarts meg, ami táplál, és ne siettesd a gyökeret.',
                'en' => 'Taurus shows stability, value, and bodily safety. Build slowly, keep what nourishes you, and do not rush the root.',
            ],
            'gemini' => [
                'hu' => 'Az Ikrek a kapcsolatot, a cserét és a két oldal párbeszédét jelentik. Kérdezz, mondd el, kösd össze a szálakat — a válasz a beszélgetésben van.',
                'en' => 'Gemini means exchange, connection, and a dialogue of two sides. Ask, speak, tie the threads — the answer lives in conversation.',
            ],
            'cancer' => [
                'hu' => 'A Rák a védelmet, az otthont és a gondoskodást hozza. Ami sebezhető, azt most óvni kell: család, emlék, belső gyermek.',
                'en' => 'Cancer brings protection, home, and care. What is vulnerable now needs shelter: family, memory, the inner child.',
            ],
            'leo' => [
                'hu' => 'Az Oroszlán a ragyogást, a szívet és a méltóságot mutatja. Állj a fénybe: a gesztusod, a játékod, a büszkeséged számít.',
                'en' => 'Leo shows radiance, heart, and dignity. Step into the light: your gesture, your play, your pride are what count.',
            ],
            'virgo' => [
                'hu' => 'A Szűz a rendet, a javítást és a hasznos részleteket jelenti. Tedd tisztába a folyamatot: ami működik, az a szeretet nyelve most.',
                'en' => 'Virgo means order, repair, and useful detail. Clean up the process: what works is the language of care right now.',
            ],
            'libra' => [
                'hu' => 'A Mérleg az egyensúlyt, a viszonyt és a szépséget hozza. A döntés akkor jó, ha mindkét oldalnak van méltósága.',
                'en' => 'Libra brings balance, relationship, and beauty. A decision is sound when both sides keep their dignity.',
            ],
            'scorpio' => [
                'hu' => 'A Skorpió a mélységet, a titkot és a gyógyító őszinteséget mutatja. Merülj alá: ami fáj, az a kulcs, nem a felszín.',
                'en' => 'Scorpio shows depth, secrecy, and healing honesty. Go under: what hurts is the key, not the surface.',
            ],
            'sagittarius' => [
                'hu' => 'A Nyilas az irányt, a jelentést és a tágas horizontot jelenti. Nézz fel: a következő lépés egy nagyobb történet része.',
                'en' => 'Sagittarius means aim, meaning, and a wide horizon. Look up: the next step belongs to a larger story.',
            ],
            'capricorn' => [
                'hu' => 'A Bak az építést, a célt és a hosszú távú formát hozza. Ami most készül, az idővel dicsőséges lesz, ha kitartasz.',
                'en' => 'Capricorn brings building, aim, and long-term form. What is being made now becomes honorable with time, if you persist.',
            ],
            'aquarius' => [
                'hu' => 'A Vízöntő a látomást, a közösséget és a szokatlan megoldást mutatja. Lépj ki a sorból: a jövő más mintát kér.',
                'en' => 'Aquarius shows vision, community, and an unusual solution. Step out of line: the future asks for a different pattern.',
            ],
            'pisces' => [
                'hu' => 'A Halak az összeolvadást, az együttérzést és a láthatatlan kötést jelentik. Bízz a folyásban: a határ most gyógyít, ha nem tagadod.',
                'en' => 'Pisces means unity, compassion, and an invisible bond. Trust the current: the boundary heals now if you do not deny it.',
            ],
        ];
    }
}
