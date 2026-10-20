<?php

namespace App\Support;

use App\Models\CardPack;
use App\Models\MeaningEntry;
use App\Models\MeaningGroup;

final class MinorArcanaMeaningCatalog
{
    public const SLUG = 'kis-arkanum';

    public static function seed(): MeaningGroup
    {
        $group = MeaningGroup::query()->updateOrCreate(
            ['slug' => self::SLUG],
            [
                'name_hu' => 'Kis Arkánum',
                'name_en' => 'Minor Arcana',
                'description_hu' => 'A Kelyhek az érzelmek és a kapcsolatok világába vezetnek. A Botok, más néven Pálcák, a kreativitást, a cselekvést és az akaratot tükrözik. Az Érmék, más helyen Korongok, a bőséget, a hiányt és az anyagi javak lelki vetületét mutatják. A Kardok a harcot, a gondolkodást, a konfliktusokat és a döntéshozatalt jelképezik.',
                'description_en' => 'Cups lead into feelings and relationships. Wands, also called rods, reflect creativity, action, and will. Pentacles, also called coins, show abundance, lack, and the inner side of material things. Swords stand for struggle, thought, conflict, and decision.',
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
            self::card('cups-ace', 'Kelyhek ásza', 'Ace of Cups', 'új érzelmi kezdet', 'new emotional beginning', 'Az új érzelmi kezdetek, a lehetőségek és a kreativitás szimbóluma.', 'A symbol of new emotional beginnings, possibilities, and creativity.'),
            self::card('cups-02', 'Kelyhek 2', 'Two of Cups', 'szeretet és harmónia', 'love and harmony', 'A szeretet és a harmónia, valamint a kapcsolatok és partnerségek erősítése.', 'Love and harmony, and the strengthening of relationships and partnerships.'),
            self::card('cups-03', 'Kelyhek 3', 'Three of Cups', 'ünneplés és barátság', 'celebration and friendship', 'Az ünneplés, a barátságok és a társasági események, amely a közösségi kapcsolatok örömét hangsúlyozza.', 'Celebration, friendship, and social gatherings, highlighting the joy of community.'),
            self::card('cups-04', 'Kelyhek 4', 'Four of Cups', 'szomorúság és melankólia', 'sadness and melancholy', 'A szomorúság és a melankólia, amely a hiányérzetről és az érzelmi zűrzavarról szól.', 'Sadness and melancholy, about a sense of lack and emotional turmoil.'),
            self::card('cups-05', 'Kelyhek 5', 'Five of Cups', 'veszteség és bánat', 'loss and grief', 'A veszteség és a bánat, amely az érzelmi csalódásokat és a múltbeli események feldolgozását jelzi.', 'Loss and grief, pointing to emotional disappointments and working through the past.'),
            self::card('cups-06', 'Kelyhek 6', 'Six of Cups', 'nosztalgia', 'nostalgia', 'A nosztalgia és a gyermekkor emlékek, amelyek az öröm és az ártatlanság visszahívásáról szólnak.', 'Nostalgia and childhood memories, calling back joy and innocence.'),
            self::card('cups-07', 'Kelyhek 7', 'Seven of Cups', 'választások és illúziók', 'choices and illusions', 'A választások és az illúziók, amelyek a vágyak és lehetőségek sokféleségét képviselik.', 'Choices and illusions, representing the many desires and possibilities.'),
            self::card('cups-08', 'Kelyhek 8', 'Eight of Cups', 'lemondás és elengedés', 'withdrawal and letting go', 'A lemondás és az elengedés, amely a múltbéli érzelmi kötelékek megszüntetését jelenti.', 'Withdrawal and letting go, ending emotional ties from the past.'),
            self::card('cups-09', 'Kelyhek 9', 'Nine of Cups', 'érzelmi beteljesülés', 'emotional fulfillment', 'Az érzelmi beteljesülés, a boldogság és az öröm szimbóluma.', 'A symbol of emotional fulfillment, happiness, and joy.'),
            self::card('cups-10', 'Kelyhek 10', 'Ten of Cups', 'családi harmónia', 'family harmony', 'A családi harmónia, a boldog otthon és az érzelmi kiteljesedés.', 'Family harmony, a happy home, and emotional fulfillment.'),
            self::card('cups-page', 'Kelyhek apródja', 'Page of Cups', 'kíváncsiság és új érzelmek', 'curiosity and new feelings', 'A kíváncsiság és az új érzelmek szimbóluma, a felfedezés és a lehetőségek nyitottá tételét jelzi.', 'A symbol of curiosity and new feelings, open to discovery and possibility.'),
            self::card('cups-knight', 'Kelyhek lovagja', 'Knight of Cups', 'érzelmi mélység és romantika', 'emotional depth and romance', 'Az érzelmi mélység és a romantika képviselője, aki szenvedélyes és érzelmes.', 'A figure of emotional depth and romance, passionate and feeling.'),
            self::card('cups-queen', 'Kelyhek királynője', 'Queen of Cups', 'szeretet és empátia', 'love and empathy', 'A szeretet és az empátia megtestesítője, aki gondoskodó és támogató, mély érzelmi intelligenciával bír.', 'Love and empathy embodied: caring, supportive, and emotionally intelligent.'),
            self::card('cups-king', 'Kelyhek királya', 'King of Cups', 'érzelmi stabilitás', 'emotional stability', 'Az érzelmi stabilitás és a bölcs vezetés szimbóluma, aki képes egyensúlyt teremteni az érzelmek és a racionalitás között.', 'Emotional stability and wise leadership, balancing feeling and reason.'),

            self::card('wands-ace', 'Botok ásza', 'Ace of Wands', 'új kezdet és inspiráció', 'new beginning and inspiration', 'Az új kezdetek, az inspiráció és a kreativitás szimbóluma.', 'A symbol of new beginnings, inspiration, and creativity.'),
            self::card('wands-02', 'Botok 2', 'Two of Wands', 'tervezés és döntés', 'planning and decision', 'A tervezés és a döntéshozatal, amely az új lehetőségek felfedezésére utal.', 'Planning and decision-making, pointing toward new possibilities.'),
            self::card('wands-03', 'Botok 3', 'Three of Wands', 'előrehaladás és siker', 'progress and success', 'Az előrehaladás és a siker, a teljesítmény és az együttműködés jelképe.', 'Progress and success, a sign of achievement and cooperation.'),
            self::card('wands-04', 'Botok 4', 'Four of Wands', 'stabilitás és biztonság', 'stability and security', 'A stabilitás és a biztonság, az otthon és a közösség harmóniája.', 'Stability and security, harmony of home and community.'),
            self::card('wands-05', 'Botok 5', 'Five of Wands', 'verseny és konfliktus', 'competition and conflict', 'A verseny és a konfliktusok, a kihívások, amelyeken át kell lépni.', 'Competition and conflict, challenges that must be moved through.'),
            self::card('wands-06', 'Botok 6', 'Six of Wands', 'győzelem és elismerés', 'victory and recognition', 'A győzelem és az elismerés, az eredmények ünneplése.', 'Victory and recognition, celebrating what has been achieved.'),
            self::card('wands-07', 'Botok 7', 'Seven of Wands', 'védekezés és állhatatosság', 'defense and perseverance', 'A védekezés és az állhatatosság, a kihívásokkal való szembenézés.', 'Defense and perseverance, facing challenges head on.'),
            self::card('wands-08', 'Botok 8', 'Eight of Wands', 'gyors cselekvés', 'swift action', 'A gyors mozgás és a cselekvés, az új lehetőségek megragadása.', 'Swift movement and action, seizing new opportunities.'),
            self::card('wands-09', 'Botok 9', 'Nine of Wands', 'kitartás', 'endurance', 'Az állhatatosság és a kitartás, a nehézségeken való túllépés.', 'Perseverance and endurance, getting through difficulty.'),
            self::card('wands-10', 'Botok 10', 'Ten of Wands', 'terhek és felelősség', 'burdens and responsibility', 'A terhek és a felelősség, a túlvállalás következménye.', 'Burdens and responsibility, the result of taking on too much.'),
            self::card('wands-page', 'Botok apródja', 'Page of Wands', 'kreatív lelkesedés', 'creative enthusiasm', 'Az új ötletek és a kreativitás iránti lelkesedés szimbóluma, aki tele van energiával és inspirációval.', 'Enthusiasm for new ideas and creativity, full of energy and inspiration.'),
            self::card('wands-knight', 'Botok lovagja', 'Knight of Wands', 'bátor kezdeményezés', 'bold initiative', 'A cselekvés és az új kihívások felé való elindulás jelképe, aki bátor és kezdeményező.', 'Setting out toward action and new challenges, brave and initiating.'),
            self::card('wands-queen', 'Botok királynője', 'Queen of Wands', 'kreativitás és függetlenség', 'creativity and independence', 'A kreativitás és a függetlenség képviselője, aki inspiráló és magabiztos.', 'Creativity and independence, inspiring and confident.'),
            self::card('wands-king', 'Botok királya', 'King of Wands', 'vezetés és hatalom', 'leadership and power', 'A vezetés és a hatalom szimbóluma, aki stratégiai gondolkodásával és bátorságával érvényesül.', 'Leadership and power, prevailing through strategy and courage.'),

            self::card('pentacles-ace', 'Érmék ásza', 'Ace of Pentacles', 'anyagi bőség', 'material abundance', 'Az új lehetőségek, az anyagi bőség és a jólét szimbóluma. Ez a lap a kezdeti sikereket és az új pénzügyi lehetőségeket jelzi.', 'A symbol of new possibilities, material abundance, and prosperity. It points to early success and new financial openings.'),
            self::card('pentacles-02', 'Érmék 2', 'Two of Pentacles', 'döntés és egyensúly', 'choice and balance', 'A döntések és a választások ideje. Az érmék kettes arra utal, hogy egyensúlyba kell hozni a különböző anyagi lehetőségeket, és meg kell találni a helyes irányt.', 'A time of decisions and choices. The two asks for balance among material options and a clear direction.'),
            self::card('pentacles-03', 'Érmék 3', 'Three of Pentacles', 'csapatmunka', 'teamwork', 'A csapatmunka és a közös erőfeszítések szimbóluma. E lap a közös célok elérését és a kreatív együttműködést hangsúlyozza, amely anyagi sikerekhez vezethet.', 'A symbol of teamwork and shared effort. It stresses common goals and creative cooperation that can lead to material success.'),
            self::card('pentacles-04', 'Érmék 4', 'Four of Pentacles', 'stabilitás és birtoklás', 'stability and holding on', 'A stabilitás és a biztonság lapja. Az érmék négyes a birtoklásra és a biztos jövő megteremtésére utal, de figyelmeztet a túlzott ragaszkodásra is.', 'The card of stability and security. It points to holding what you have and building a sure future, and warns against clinging too tightly.'),
            self::card('pentacles-05', 'Érmék 5', 'Five of Pentacles', 'nehézség és veszteség', 'hardship and loss', 'A nehézségek és veszteségek lapja. Az érmék ötös arra figyelmeztet, hogy a pénzügyi vagy érzelmi válságok ellenére is meg kell találni a reményt.', 'The card of hardship and loss. It warns that hope still has to be found through financial or emotional crisis.'),
            self::card('pentacles-06', 'Érmék 6', 'Six of Pentacles', 'bőség megosztása', 'sharing abundance', 'A bőség megosztása és a generozitás szimbóluma. Ez a lap a társadalmi kapcsolatok fontosságát hangsúlyozza, és azt, hogy az adományozás örömöt hozhat.', 'A symbol of sharing abundance and generosity. It stresses social bonds, and that giving can bring joy.'),
            self::card('pentacles-07', 'Érmék 7', 'Seven of Pentacles', 'befektetés és türelem', 'investment and patience', 'A befektetések és a várakozás lapja. Az érmék hetes a türelem és a kitartás fontosságát hangsúlyozza, miközben az eredményekre várunk.', 'The card of investment and waiting. It stresses patience and persistence while the results are still ahead.'),
            self::card('pentacles-08', 'Érmék 8', 'Eight of Pentacles', 'kemény munka', 'hard work', 'A kemény munka és a szakmai fejlődés szimbóluma. Ez a lap az elkötelezettséget és a tanulás fontosságát emeli ki az anyagi siker érdekében.', 'A symbol of hard work and professional growth. It highlights commitment and learning in the service of material success.'),
            self::card('pentacles-09', 'Érmék 9', 'Nine of Pentacles', 'anyagi függetlenség', 'material independence', 'Az anyagi függetlenség és a jólét lapja. Az érmék kilences a munka és az erőfeszítések gyümölcseinek élvezetére utal, valamint az önmegvalósítás fontosságára.', 'The card of material independence and prosperity. It points to enjoying the fruits of work, and to self-realization.'),
            self::card('pentacles-10', 'Érmék 10', 'Ten of Pentacles', 'tartós gazdagság', 'lasting wealth', 'A tartós gazdagság és a családi örökség szimbóluma. Az érmék tizes a biztonságot, a stabilitást és a generációkon átívelő sikerességet jelképezi.', 'A symbol of lasting wealth and family legacy. It stands for security, stability, and success across generations.'),
            self::card('pentacles-page', 'Érmék apródja', 'Page of Pentacles', 'tanulás és új lehetőség', 'study and new opportunity', 'Az új lehetőségek felfedezése és a gyakorlati tanulmányok szimbóluma, aki kíváncsi és tanulásra nyitott.', 'Discovering new possibilities and practical study, curious and ready to learn.'),
            self::card('pentacles-knight', 'Érmék lovagja', 'Knight of Pentacles', 'szorgalom és megbízhatóság', 'diligence and reliability', 'A szorgalom és a megbízhatóság képviselője, aki céltudatos és kitartó.', 'Diligence and reliability, purposeful and persistent.'),
            self::card('pentacles-queen', 'Érmék királynője', 'Queen of Pentacles', 'bőség és gondoskodás', 'abundance and care', 'A bőség és a stabilitás szimbóluma, aki gondoskodó és figyelmes a környezetére.', 'Abundance and stability, caring and attentive to the surroundings.'),
            self::card('pentacles-king', 'Érmék királya', 'King of Pentacles', 'siker és pénzügyi stabilitás', 'success and financial stability', 'A siker és a pénzügyi stabilitás megtestesítője, aki okos döntéseivel irányítja a világát.', 'Success and financial stability, directing the world through sound decisions.'),

            self::card('swords-ace', 'Kardok ásza', 'Ace of Swords', 'világos gondolkodás', 'clear thinking', 'Az új ötletek és a világos gondolkodás szimbóluma. Ez a lap a tudás és az igazság keresését jelzi.', 'A symbol of new ideas and clear thinking. It points to the search for knowledge and truth.'),
            self::card('swords-02', 'Kardok 2', 'Two of Swords', 'döntés és kétely', 'decision and doubt', 'A döntés és a választás ideje. Ez a lap a kételyeket és a konfliktusokat jelzi, amelyek a döntéshozatal előtt állnak.', 'A time of decision and choice. It shows the doubts and conflicts that stand before a decision.'),
            self::card('swords-03', 'Kardok 3', 'Three of Swords', 'fájdalom és veszteség', 'pain and loss', 'A fájdalom és a veszteség lapja. Ez a kártya a szívfájdalomra, a csalódásra és a kapcsolati problémákra utal.', 'The card of pain and loss. It points to heartache, disappointment, and trouble in relationships.'),
            self::card('swords-04', 'Kardok 4', 'Four of Swords', 'pihenés és gyógyulás', 'rest and recovery', 'A pihenés és a gyógyulás ideje. Ez a lap a visszavonulásra és a belső megújulásra figyelmeztet.', 'A time of rest and healing. It calls for withdrawal and inner renewal.'),
            self::card('swords-05', 'Kardok 5', 'Five of Swords', 'konfliktus és versengés', 'conflict and rivalry', 'A konfliktus és a versengés lapja. Ez a kártya a harcokat és a vitákat szimbolizálja, amelyek a győzelemért folynak.', 'The card of conflict and rivalry. It symbolizes fights and arguments waged for victory.'),
            self::card('swords-06', 'Kardok 6', 'Six of Swords', 'változás és elmozdulás', 'change and passage', 'A változás és az elmozdulás szimbóluma. Ez a lap a gyógyulásról és a nehézségek leküzdéséről szól.', 'A symbol of change and moving on. It speaks of healing and getting past difficulty.'),
            self::card('swords-07', 'Kardok 7', 'Seven of Swords', 'stratégia és titkolózás', 'strategy and secrecy', 'A stratégia és a titkolózás lapja. Ez a kártya az óvatosságot és a rejtett szándékokat jelzi.', 'The card of strategy and secrecy. It points to caution and hidden intentions.'),
            self::card('swords-08', 'Kardok 8', 'Eight of Swords', 'korlátozottság és félelem', 'restriction and fear', 'A korlátozottság és a félelem érzése. Ez a lap a belső akadályokról és a bátortalanságról szól.', 'A feeling of restriction and fear. It speaks of inner obstacles and a lack of courage.'),
            self::card('swords-09', 'Kardok 9', 'Nine of Swords', 'aggodalom', 'worry', 'Az aggodalom és a rémálmok lapja. Ez a kártya a belső félelmekkel és szorongásokkal foglalkozik.', 'The card of worry and nightmares. It deals with inner fears and anxiety.'),
            self::card('swords-10', 'Kardok 10', 'Ten of Swords', 'végső veszteség', 'final loss', 'A végső veszteség és a szenvedés szimbóluma. Ez a lap a nehézségek végső megoldásáról és a gyógyulásról szól.', 'A symbol of final loss and suffering. It speaks of an ending to hardship, and of healing.'),
            self::card('swords-page', 'Kardok apródja', 'Page of Swords', 'kritikai gondolkodás', 'critical thinking', 'Az új ötletek és a kritikai gondolkodás szimbóluma, aki kíváncsi és bátor.', 'New ideas and critical thinking, curious and brave.'),
            self::card('swords-knight', 'Kardok lovagja', 'Knight of Swords', 'impulzív bátorság', 'impulsive courage', 'A cselekvés és a kihívások iránti bátorság megtestesítője, aki impulzív és határozott.', 'Courage toward action and challenge, impulsive and decisive.'),
            self::card('swords-queen', 'Kardok királynője', 'Queen of Swords', 'intelligencia és igazság', 'intelligence and truth', 'Az intelligencia és az igazság képviselője, aki logikus és racionális, valamint érzékeny a környezetére.', 'Intelligence and truth, logical and rational, and sensitive to the surroundings.'),
            self::card('swords-king', 'Kardok királya', 'King of Swords', 'éles elme és vezetés', 'sharp mind and leadership', 'A hatalom és a vezetés szimbóluma, aki éles elméjével és bátor döntéseivel irányít.', 'Power and leadership, directing through a sharp mind and brave decisions.'),
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
