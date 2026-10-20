<?php

namespace App\Support;

use App\Models\CardPack;
use App\Models\MeaningEntry;
use App\Models\MeaningGroup;

final class MajorArcanaMeaningCatalog
{
    public const SLUG = 'nagy-arkanum';

    public static function seed(): MeaningGroup
    {
        $group = MeaningGroup::query()->updateOrCreate(
            ['slug' => self::SLUG],
            [
                'name_hu' => 'Nagy Arkánum',
                'name_en' => 'Major Arcana',
                'description_hu' => 'A Nagy Arkánum, vagy Nagy Arcana 22 figurális lapból áll.',
                'description_en' => 'The Major Arcana, or Greater Arcana, consists of 22 figurative cards.',
                'type' => CardPack::TYPE_TAROT,
                'owner_id' => null,
            ],
        );

        $keep = [];
        foreach (self::entries() as $index => $entry) {
            $keep[] = $entry['figure_id'];
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

        $group->entries()->whereNotIn('figure_id', $keep)->delete();

        return $group;
    }

    /**
     * @return list<array{figure_id: string, name_hu: string, name_en: string, meaning_hu: string, meaning_en: string, description_hu: string, description_en: string}>
     */
    public static function entries(): array
    {
        return [
            self::card(
                '00-the-fool',
                'A Bolond',
                'The Fool',
                'sors, szerencse, végzet',
                'fate, fortune, destiny',
                '0. vagy XXII. Eredetileg nem számozott, a mai francia kártya Jokerének korai megfelelője lehetett. Sors, szerencse, vég(zet).',
                '0 or XXII. Originally unnumbered, it may have been an early counterpart of the Joker in the modern French deck. Fate, fortune, destiny.',
            ),
            self::card(
                '01-the-magician',
                'A Mágus / A Varázsló',
                'The Magician',
                'tudást kereső ember',
                'a person seeking knowledge',
                'Tudást kereső ember.',
                'A person seeking knowledge.',
            ),
            self::card(
                '02-the-high-priestess',
                'A Főpapnő / A Nőpápa / A Papnő',
                'The High Priestess',
                'intuíció, inspiráció',
                'intuition, inspiration',
                'Intuíció, inspiráció, vagy annak hiánya.',
                'Intuition, inspiration, or the lack of them.',
            ),
            self::card(
                '03-the-empress',
                'A Császárnő / Az Uralkodónő',
                'The Empress',
                'nőiesség, szépség',
                'femininity, beauty',
                'Nőiesség, szépség.',
                'Femininity, beauty.',
            ),
            self::card(
                '04-the-emperor',
                'A Császár / Az Uralkodó',
                'The Emperor',
                'férfiasság, cselekvés',
                'masculinity, action',
                'Férfiasság, cselekvés.',
                'Masculinity, action.',
            ),
            self::card(
                '05-the-hierophant',
                'Pápa / Főpap / A Pap',
                'The Hierophant',
                'tanács',
                'counsel',
                'Tanács.',
                'Counsel.',
            ),
            self::card(
                '06-the-lovers',
                'A Szerelmesek / A Szeretők / A Választás',
                'The Lovers',
                'döntés',
                'decision',
                'Döntés.',
                'Decision.',
            ),
            self::card(
                '07-the-chariot',
                'A Diadalszekér / A Kocsihajtó',
                'The Chariot',
                'eredmények, siker',
                'results, success',
                'Eredmények, siker, vagy a kudarc veszélye.',
                'Results, success, or the danger of failure.',
            ),
            self::card(
                '08-strength',
                'Az Állhatatosság / Az Erő',
                'Strength',
                'céltudatosság',
                'sense of purpose',
                'Céltudatosság.',
                'Sense of purpose.',
            ),
            self::card(
                '09-the-hermit',
                'A Remete',
                'The Hermit',
                'idő, bölcsesség, visszahúzódás',
                'time, wisdom, withdrawal',
                'Idő, bölcsesség, visszahúzódás.',
                'Time, wisdom, withdrawal.',
            ),
            self::card(
                '10-wheel-of-fortune',
                'A Szerencsekerék',
                'Wheel of Fortune',
                'változás, megfontoltság',
                'change, prudence',
                'Változás, megfontoltság, örök visszatérés.',
                'Change, prudence, eternal return.',
            ),
            self::card(
                '11-justice',
                'Az Igazság / Az Igazságszolgáltatás',
                'Justice',
                'saját sors irányítása',
                'directing one\'s own fate',
                'Óvatosság a tanácsok elfogadásában, saját sors irányítása.',
                'Caution in accepting advice, directing one\'s own fate.',
            ),
            self::card(
                '12-the-hanged-man',
                'Az Akasztott ember / A Felfüggesztett ember',
                'The Hanged Man',
                'kényszerhelyzet, rugalmasság',
                'constraint, flexibility',
                'Kényszerhelyzet, rugalmasság, alapvető változások.',
                'A forced situation, flexibility, fundamental changes.',
            ),
            self::card(
                '13-death',
                'A Halál / A Nagy Arkánum',
                'Death',
                'változás átalakulás útján',
                'change through transformation',
                'Változás átalakulás útján.',
                'Change by way of transformation.',
            ),
            self::card(
                '14-temperance',
                'A Megfontoltság / Az Angyal / A Mértékletesség',
                'Temperance',
                'mértékletesség, könyörület',
                'temperance, mercy',
                'Mértékletesség, könyörület.',
                'Temperance, mercy.',
            ),
            self::card(
                '15-the-devil',
                'Az Ördög',
                'The Devil',
                'testi vágyak, vonzalom',
                'bodily desires, attraction',
                'Testi vágyak, vonzalom, ellenség, óvatosság.',
                'Bodily desires, attraction, an enemy, caution.',
            ),
            self::card(
                '16-the-tower',
                'A Torony',
                'The Tower',
                'büntetés, büszkeség',
                'punishment, pride',
                'Büntetés, büszkeség, isteni sugallat.',
                'Punishment, pride, divine inspiration.',
            ),
            self::card(
                '17-the-star',
                'A Csillag',
                'The Star',
                'új kezdet, öröm, üdvözülés',
                'new beginning, joy, salvation',
                'Új kezdet, öröm, üdvözülés.',
                'A new beginning, joy, salvation.',
            ),
            self::card(
                '18-the-moon',
                'A Hold',
                'The Moon',
                'változékonyság, bizonytalanság',
                'changeability, uncertainty',
                'Változékonyság, bizonytalanság, félelmek, tudatalatti.',
                'Changeability, uncertainty, fears, the unconscious.',
            ),
            self::card(
                '19-the-sun',
                'A Nap',
                'The Sun',
                'pompa, egészség, boldogság',
                'splendor, health, happiness',
                'Pompa, egészség, vonzalom, gazdagság, boldogság.',
                'Splendor, health, attraction, wealth, happiness.',
            ),
            self::card(
                '20-judgement',
                'Az Utolsó Ítélet / Az Ítélet',
                'Judgement',
                'büntetés vagy jutalom',
                'punishment or reward',
                'Büntetés vagy jutalom, a végső cél elérése.',
                'Punishment or reward, reaching the final aim.',
            ),
            self::card(
                '21-the-world',
                'A Világ',
                'The World',
                'beteljesülés, harmónia',
                'fulfillment, harmony',
                'Beteljesülés, harmónia.',
                'Fulfillment, harmony.',
            ),
        ];
    }

    /**
     * @return array{figure_id: string, name_hu: string, name_en: string, meaning_hu: string, meaning_en: string, description_hu: string, description_en: string}
     */
    private static function card(
        string $id,
        string $nameHu,
        string $nameEn,
        string $meaningHu,
        string $meaningEn,
        string $descriptionHu,
        string $descriptionEn,
    ): array {
        return [
            'figure_id' => $id,
            'name_hu' => $nameHu,
            'name_en' => $nameEn,
            'meaning_hu' => $meaningHu,
            'meaning_en' => $meaningEn,
            'description_hu' => $descriptionHu,
            'description_en' => $descriptionEn,
        ];
    }
}
