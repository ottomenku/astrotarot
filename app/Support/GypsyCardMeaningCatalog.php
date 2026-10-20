<?php

namespace App\Support;

use App\Models\CardPack;
use App\Models\MeaningEntry;
use App\Models\MeaningGroup;

final class GypsyCardMeaningCatalog
{
    public const SLUG = 'ciganykartya';

    public static function seed(): MeaningGroup
    {
        $group = MeaningGroup::query()->updateOrCreate(
            ['slug' => self::SLUG],
            [
                'name_hu' => 'Cigánykártya',
                'name_en' => 'Gypsy fortune cards',
                'description_hu' => 'A hagyományos 36 lapos cigánykártya jelentései.',
                'description_en' => 'Meanings for the traditional 36-card gypsy fortune deck.',
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

        CardPack::query()
            ->where('slug', 'cigany-kartya')
            ->whereNull('meaning_group_id')
            ->update(['meaning_group_id' => $group->id]);

        return $group;
    }

    /**
     * @return list<array{figure_id: string, name_hu: string, name_en: string, meaning_hu: string, meaning_en: string, description_hu: string, description_en: string}>
     */
    public static function entries(): array
    {
        return [
            self::card('ajandek', 'Ajándék', 'Gift', 'örömteli fordulat', 'joyful turn', 'Örömteli fordulat.', 'A joyful turn.'),
            self::card('allandossag', 'Állandóság', 'Constancy', 'stabilitás, változatlanság', 'stability, constancy', 'Stabilitás, változatlanság. „Isten szeme”, ami történik, felsőbb akarat szerint van.', 'Stability, constancy. “The eye of God”: what happens is according to a higher will.'),
            self::card('betegseg', 'Betegség', 'Illness', 'testi vagy lelki betegség', 'bodily or emotional illness', 'Testi vagy lelki betegség. Rossz lelkiállapot, akadályozottság.', 'Bodily or emotional illness. A poor state of mind, a sense of being blocked.'),
            self::card('biro', 'Bíró', 'Judge', 'hivatalos döntés', 'official decision', 'Hivatalos személy, komoly döntés.', 'An official person, a serious decision.'),
            self::card('bosszusag', 'Bosszúság', 'Annoyance', 'kellemetlenség, ingerlékenység', 'nuisance, irritability', 'Kellemetlenség, ingerlékenység.', 'Nuisance, irritability.'),
            self::card('ellenseg', 'Ellenség', 'Enemy', 'rosszakaró, vetélytárs', 'ill-wisher, rival', 'Rosszakaró, vetélytárs, intrikus személy. Saját árnyoldalunk.', 'An ill-wisher, a rival, an intriguer. Our own shadow side.'),
            self::card('feltekenyseg', 'Féltékenység', 'Jealousy', 'féltékenység, félelem', 'jealousy, fear', 'Féltékenység, féltés, félelem.', 'Jealousy, possessiveness, fear.'),
            self::card('gondolat', 'Gondolat', 'Thought', 'szőke férfi, tervezgetés', 'fair-haired man, planning', 'Szőke férfi vagy szellemi tevékenység, tervezgetés.', 'A fair-haired man, or mental activity and planning.'),
            self::card('gyermek', 'Gyermek', 'Child', 'gyermek vagy pletyka', 'child or gossip', 'A kérdező gyermekei vagy szóbeszéd, pletyka.', 'The questioner’s children, or talk and gossip.'),
            self::card('halal', 'Halál', 'Death', 'sorsfordulat', 'turning of fate', 'Kedvezőtlen, de nem feltétlenül tragikus sorsfordulat.', 'An unfavourable, but not necessarily tragic, turning of fate.'),
            self::card('hamissag', 'Hamisság', 'Falsehood', 'becsapás veszélye', 'risk of deceit', 'Becsapás veszélye, valami meghiúsulhat.', 'A risk of deceit; something may fall through.'),
            self::card('haz', 'Ház', 'House', 'otthon, szülői ház', 'home, family house', 'Otthon, haza, szülői ház.', 'Home, homeland, the parental house.'),
            self::card('hazassag', 'Házasság', 'Marriage', 'család, szövetség', 'family, alliance', 'Saját család, házasság, esetleg üzleti szövetség.', 'One’s own family, marriage, or possibly a business alliance.'),
            self::card('huseg', 'Hűség', 'Fidelity', 'őszinteség, kitartás', 'honesty, loyalty', 'Őszinteség, kitartás.', 'Honesty, loyalty.'),
            self::card('katonatiszt', 'Katonatiszt', 'Officer', 'sötét hajú férfi', 'dark-haired man', 'Sötét hajú férfi. Munkahelyi kötelezettségek.', 'A dark-haired man. Duties at work.'),
            self::card('keves-penz', 'Kevés pénz', 'Little money', 'kisebb összeg', 'a smaller sum', 'Kisebb összeg, filléres gondok.', 'A smaller sum, petty money worries.'),
            self::card('latogatas', 'Látogatás', 'Visit', 'vendég, bizonytalanság', 'guest, uncertainty', 'Vendég. Bizonytalanság, esetlegesség.', 'A guest. Uncertainty, something incidental.'),
            self::card('lelkesz', 'Lelkész', 'Clergyman', 'tiszta szándék, segítő', 'clean intent, helper', 'Mély lelki élet. Tiszta szándékok. Segítő.', 'A deep inner life. Clean intentions. A helper.'),
            self::card('level', 'Levél', 'Letter', 'levél, hivatalos irat', 'letter, official paper', 'Levél, meghívó, hivatalos irat.', 'A letter, an invitation, an official paper.'),
            self::card('orvendezes', 'Örvendezés', 'Rejoicing', 'örömünnep, optimizmus', 'celebration, optimism', 'Örömünnep, családi esemény, optimizmus.', 'A celebration, a family occasion, optimism.'),
            self::card('ozvegyasszony', 'Özvegyasszony', 'Widow', 'idősebb hölgy', 'older woman', 'Idősebb hölgy, sokszor a kérdező édesanyja.', 'An older woman, often the questioner’s mother.'),
            self::card('ozvegy-ferfi', 'Özvegy férfi', 'Widower', 'idősebb úr', 'older man', 'Idősebb úr, sokszor az édesapa.', 'An older gentleman, often the father.'),
            self::card('penz', 'Pénz', 'Money', 'nagyobb összeg', 'a larger sum', 'Nagyobb összeg.', 'A larger sum.'),
            self::card('remeny', 'Remény', 'Hope', 'hosszú idejű megvalósulás', 'a long unfolding', 'Reménység. Hosszú idejű megvalósulás.', 'Hope. Something that comes to pass over a long time.'),
            self::card('szerelem', 'Szerelem', 'Love', 'szerelem, szeretet, öröm', 'love, affection, joy', 'Szerelem, szeretet, öröm.', 'Love, affection, joy.'),
            self::card('szerelmes-no', 'Szerelmes nő', 'Woman in love', 'szív királynő', 'queen of hearts', '„Szív királynő”. A kérdezőt jelenti, ha a kérdező nő. Ha férfi a kérdező, akkor a jelenlegi vagy jövendő párját jelenti.', '“Queen of hearts.” She stands for the questioner when the questioner is a woman. When the questioner is a man, she stands for his present or future partner.'),
            self::card('szerencse', 'Szerencse', 'Fortune', 'jó szerencse', 'good fortune', 'Jó szerencse, kedvező fordulat.', 'Good fortune, a favourable turn.'),
            self::card('szerencsetlenseg', 'Szerencsétlenség', 'Misfortune', 'sikertelenség, balszerencse', 'failure, bad luck', 'Sikertelenség, kellemetlenség, balszerencse.', 'Failure, unpleasantness, bad luck.'),
            self::card('szereto', 'Szerető', 'Lover', 'szív király', 'king of hearts', '„Szív király”. A kérdezőt jelenti, ha a kérdező férfi. Ha nő a kérdező, akkor a jelenlegi vagy jövendőbeli párja.', '“King of hearts.” He stands for the questioner when the questioner is a man. When the questioner is a woman, he stands for her present or future partner.'),
            self::card('szomorusag', 'Szomorúság', 'Sadness', 'szőke nő, rosszkedv', 'fair-haired woman, low spirits', 'Szőke nő, vagy rosszkedv, szomorúság, lehangoltság.', 'A fair-haired woman, or low spirits, sadness, dejection.'),
            self::card('tolvaj', 'Tolvaj', 'Thief', 'károsítás', 'being harmed', 'Valaki meg akar károsítani, anyagi vagy lelki értelemben.', 'Someone wants to harm you, materially or emotionally.'),
            self::card('utazas', 'Utazás', 'Journey', 'gyors bekövetkezés, utazás', 'swift events, a journey', 'Az események gyors bekövetkezése. Utazás.', 'Events arriving quickly. A journey.'),
            self::card('uzenet', 'Üzenet', 'Message', 'gyors hír', 'swift news', 'Egy esemény nagyon gyors bekövetkezése. Hír, üzenet.', 'An event arriving very quickly. News, a message.'),
            self::card('vagy', 'Vágy', 'Desire', 'sötét hajú nő, vágyakozás', 'dark-haired woman, longing', 'Sötét hajú nő. Vágyakozás, sóvárgás.', 'A dark-haired woman. Longing, yearning.'),
            self::card('varatlan-orom', 'Váratlan öröm', 'Unexpected joy', 'hirtelen szerencse', 'sudden fortune', 'Hirtelen szerencse, nem várt anyagi javak.', 'Sudden fortune, unexpected material goods.'),
            self::card('veszteseg', 'Veszteség', 'Loss', 'kár, anyagi veszteség', 'harm, material loss', 'A kérdező vesztesnek érzi magát. Kár, anyagi veszteség.', 'The questioner feels like the loser. Harm, material loss.'),
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
