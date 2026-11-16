<?php

declare(strict_types=1);

require dirname(__DIR__, 3).'/vendor/autoload.php';

$dir = __DIR__;

function writeJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    file_put_contents($path, $json."\n");
}

function record(string $id, string $kind, string $text, array $fields): array
{
    return array_merge([
        'id' => $id,
        'kind' => $kind,
        'text' => $text,
    ], $fields);
}

$majorExtras = [
    '00-the-fool' => ['light_hu' => 'Nyitott lépés, szerencse, a még nem írt út.', 'shadow_hu' => 'Vakmerőség, vagy a sorsra hárított döntés.', 'life_hu' => 'Kezdet, kockázat, út, a személy sorsa.', 'light_en' => 'An open step, luck, a road not yet written.', 'shadow_en' => 'Recklessness, or handing the decision to fate.', 'life_en' => 'Beginnings, risk, the road, personal fate.'],
    '01-the-magician' => ['light_hu' => 'A tudás eszközei a kézben vannak.', 'shadow_hu' => 'Üres mutatvány, vagy a tudás visszatartása.', 'life_hu' => 'Tanulás, mesterség, akarat, önmegmutatás.', 'light_en' => 'The tools of knowledge are in hand.', 'shadow_en' => 'An empty performance, or knowledge withheld.', 'life_en' => 'Learning, craft, will, showing oneself.'],
    '02-the-high-priestess' => ['light_hu' => 'A csendben megérkező sejtés.', 'shadow_hu' => 'Elszigetelt titok, vagy az intuíció tagadása.', 'life_hu' => 'Belső tudás, álom, tanítás, ami még nincs kimondva.', 'light_en' => 'An intuition that arrives in silence.', 'shadow_en' => 'An isolated secret, or a denied intuition.', 'life_en' => 'Inner knowledge, dreams, teaching, what is not yet said.'],
    '03-the-empress' => ['light_hu' => 'Termékenység, érzéki jelenlét, gondoskodó bőség.', 'shadow_hu' => 'Eltúlzott kényeztetés, vagy a szépség mögé rejtett üresség.', 'life_hu' => 'Test, otthon, alkotás, női elv, természet.', 'light_en' => 'Fertility, sensual presence, caring abundance.', 'shadow_en' => 'Over-indulgence, or emptiness hidden behind beauty.', 'life_en' => 'Body, home, making, the feminine principle, nature.'],
    '04-the-emperor' => ['light_hu' => 'Határ, rend, cselekvő felelősség.', 'shadow_hu' => 'Merev uralom, vagy a cselekvés hiánya.', 'life_hu' => 'Struktúra, apa-elv, munka, döntés, védelem.', 'light_en' => 'A boundary, order, responsible action.', 'shadow_en' => 'Rigid rule, or a failure to act.', 'life_en' => 'Structure, the paternal principle, work, decision, protection.'],
    '05-the-hierophant' => ['light_hu' => 'Használható tanács, hagyomány, amely utat mutat.', 'shadow_hu' => 'Vak engedelmesség, vagy a tanács elutasítása dacból.', 'life_hu' => 'Tanító, intézmény, hit, szabály, mentor.', 'light_en' => 'Useful counsel, a tradition that shows a way.', 'shadow_en' => 'Blind obedience, or rejecting counsel out of defiance.', 'life_en' => 'Teacher, institution, belief, rule, mentor.'],
    '06-the-lovers' => ['light_hu' => 'Tiszta választás, két út közül a vállalt.', 'shadow_hu' => 'Halogatott döntés, vagy a másikat hibáztató kötés.', 'life_hu' => 'Választás, szövetség, érték, szerelem mint döntés.', 'light_en' => 'A clear choice, the road that is actually taken.', 'shadow_en' => 'A delayed decision, or a bond that blames the other.', 'life_en' => 'Choice, alliance, values, love as a decision.'],
    '07-the-chariot' => ['light_hu' => 'Összeszedett akarat, megérkező eredmény.', 'shadow_hu' => 'Széteső irányítás, vagy a siker árán elvesztett kormány.', 'life_hu' => 'Cél, utazás, győzelem, önfegyelem.', 'light_en' => 'Gathered will, a result that arrives.', 'shadow_en' => 'Control coming apart, or winning while losing the reins.', 'life_en' => 'Aim, travel, victory, self-command.'],
    '08-strength' => ['light_hu' => 'Csendes céltudat, az erő amely nem törik meg.', 'shadow_hu' => 'Erőlködés, vagy a cél feladása az első ellenállásnál.', 'life_hu' => 'Bátorság, türelem, ösztön és akarat együtt.', 'light_en' => 'Quiet purpose, strength that does not break.', 'shadow_en' => 'Strain, or dropping the aim at the first resistance.', 'life_en' => 'Courage, patience, instinct held with will.'],
    '09-the-hermit' => ['light_hu' => 'Idő, amely bölcsességet érlel, tudatos visszahúzódás.', 'shadow_hu' => 'Bezárkózás, vagy a tanács nélküli magány.', 'life_hu' => 'Keresés, mentor, éjszaka, egyedül megértett igazság.', 'light_en' => 'Time that ripens wisdom, a chosen withdrawal.', 'shadow_en' => 'Shutting the door, or solitude without counsel.', 'life_en' => 'Search, a mentor, night, a truth understood alone.'],
    '10-wheel-of-fortune' => ['light_hu' => 'Fordulat, amely új helyet ad, ha a változást felismered.', 'shadow_hu' => 'Sodródás, vagy a kerék elleni merev kapaszkodás.', 'life_hu' => 'Sorsforduló, időzítés, ciklus, ami visszatér.', 'light_en' => 'A turn that offers a new place once the change is seen.', 'shadow_en' => 'Drifting, or clinging rigidly against the wheel.', 'life_en' => 'A turn of fate, timing, a cycle that returns.'],
    '11-justice' => ['light_hu' => 'Saját sors kézbe vétele, mérlegelt döntés.', 'shadow_hu' => 'Más tanácsának vak követése, vagy önbíráskodó keménység.', 'life_hu' => 'Szerződés, következmény, igazság, felelősség.', 'light_en' => 'Taking one’s own fate in hand, a weighed decision.', 'shadow_en' => 'Following advice blindly, or a harsh private verdict.', 'life_en' => 'Contract, consequence, truth, responsibility.'],
    '12-the-hanged-man' => ['light_hu' => 'A kényszerhelyzetben megtalált más nézőpont.', 'shadow_hu' => 'Áldozati póz, vagy a szükséges változás elutasítása.', 'life_hu' => 'Várakozás, felfüggesztés, nézőpontváltás.', 'light_en' => 'Another viewpoint found inside a forced pause.', 'shadow_en' => 'A pose of sacrifice, or refusing the change that is due.', 'life_en' => 'Waiting, suspension, a change of view.'],
    '13-death' => ['light_hu' => 'Átalakulás, amely lezárja a már nem élő formát.', 'shadow_hu' => 'A véget tagadó kapaszkodás, vagy a változás erőltetett rombolása.', 'life_hu' => 'Lezárás, megújulás, ami elmúlik, hogy más kezdődhessen.', 'light_en' => 'Transformation that closes a form no longer alive.', 'shadow_en' => 'Clinging to an ending, or forcing change into wreckage.', 'life_en' => 'An ending, renewal, what passes so something else can start.'],
    '14-temperance' => ['light_hu' => 'Mérték, keverés, könyörület a szélsőségek helyett.', 'shadow_hu' => 'Langyos halogatás, vagy a mérték nélküli kilengés.', 'life_hu' => 'Gyógyulás, középut, idő, két erő összeillesztése.', 'light_en' => 'Measure, mixing, mercy instead of extremes.', 'shadow_en' => 'Lukewarm delay, or a swing with no measure.', 'life_en' => 'Healing, the middle way, time, joining two forces.'],
    '15-the-devil' => ['light_hu' => 'A vonzalom felismerése, a kötés, amit néven lehet nevezni.', 'shadow_hu' => 'Testi vagy lelki függés, ellenség, óvatlan vonzás.', 'life_hu' => 'Vágy, szokás, hatalmi kötés, árnyék, ami a sajátunk.', 'light_en' => 'Seeing the attraction, a bond that can be named.', 'shadow_en' => 'Bodily or inner dependence, an enemy, careless attraction.', 'life_en' => 'Desire, habit, a bond of power, a shadow that is one’s own.'],
    '16-the-tower' => ['light_hu' => 'A büszke szerkezet leomlása után érkező sugallat.', 'shadow_hu' => 'Büntetésként megélt összeomlás, vagy a figyelmeztetés semmibevétele.', 'life_hu' => 'Váratlan törés, hamis biztonság, ébredés.', 'light_en' => 'An inspiration that arrives after a proud structure falls.', 'shadow_en' => 'Collapse lived only as punishment, or ignoring the warning.', 'life_en' => 'A sudden break, false safety, waking up.'],
    '17-the-star' => ['light_hu' => 'Új kezdet, öröm, a vihar utáni tiszta remény.', 'shadow_hu' => 'Üres ígéret, vagy a remény, amely nem lép.', 'life_hu' => 'Gyógyulás, irány, hit a folytatásban.', 'light_en' => 'A new beginning, joy, clear hope after the storm.', 'shadow_en' => 'An empty promise, or hope that never takes a step.', 'life_en' => 'Healing, direction, faith that there is a continuation.'],
    '18-the-moon' => ['light_hu' => 'A bizonytalanság, amely a tudattalant komolyan veszi.', 'shadow_hu' => 'Félelem, köd, változékonyság, amely eltérít.', 'life_hu' => 'Álom, szorongás, ösztön, ami még nincs nappali fénynél.', 'light_en' => 'Uncertainty that takes the unconscious seriously.', 'shadow_en' => 'Fear, fog, changeability that leads astray.', 'life_en' => 'Dream, anxiety, instinct not yet seen by daylight.'],
    '19-the-sun' => ['light_hu' => 'Egészség, öröm, látható siker, meleg jelenlét.', 'shadow_hu' => 'Pompa üresen, vagy a fény, amely elvakít.', 'life_hu' => 'Életerő, gyermek, elismerés, világos válasz.', 'light_en' => 'Health, joy, visible success, a warm presence.', 'shadow_en' => 'Empty splendor, or a light that blinds.', 'life_en' => 'Vitality, a child, recognition, a clear answer.'],
    '20-judgement' => ['light_hu' => 'Ébredés, a számadás, amely jutalomként zárul.', 'shadow_hu' => 'Önmarcangoló ítélet, vagy a hívás, amelyre nincs válasz.', 'life_hu' => 'Hivatás, felébredés, múlt lezárása, végső cél.', 'light_en' => 'Waking, a reckoning that closes as a reward.', 'shadow_en' => 'A self-punishing verdict, or a call that gets no answer.', 'life_en' => 'Calling, awakening, closing the past, a final aim.'],
    '21-the-world' => ['light_hu' => 'Beteljesült kör, harmónia, a hely, ahová megérkeztél.', 'shadow_hu' => 'Lezáratlan kör, vagy a teljesség, amely már nem mozdul.', 'life_hu' => 'Befejezés, világban való hely, tánc, integráció.', 'light_en' => 'A completed circle, harmony, the place you have reached.', 'shadow_en' => 'An unfinished circle, or a completeness that no longer moves.', 'life_en' => 'Completion, a place in the world, dance, integration.'],
];

$suits = [
    'cups' => ['hu' => 'Kelyhek', 'en' => 'Cups', 'element_hu' => 'víz', 'element_en' => 'water', 'life_hu' => 'Érzelmek, szeretet, család, belső hangulat, kapcsolat.', 'life_en' => 'Feelings, love, family, inner mood, relationship.'],
    'wands' => ['hu' => 'Botok', 'en' => 'Wands', 'element_hu' => 'tűz', 'element_en' => 'fire', 'life_hu' => 'Akarat, tett, kreativitás, vállalkozás, bátorság.', 'life_en' => 'Will, action, creativity, enterprise, courage.'],
    'pentacles' => ['hu' => 'Érmék', 'en' => 'Pentacles', 'element_hu' => 'föld', 'element_en' => 'earth', 'life_hu' => 'Munka, pénz, test, mesterség, anyagi biztonság.', 'life_en' => 'Work, money, the body, craft, material security.'],
    'swords' => ['hu' => 'Kardok', 'en' => 'Swords', 'element_hu' => 'levegő', 'element_en' => 'air', 'life_hu' => 'Gondolat, szó, konfliktus, döntés, igazság.', 'life_en' => 'Thought, speech, conflict, decision, truth.'],
];

$ranks = [
    'ace' => ['hu' => 'A szín elve nyers kezdetként, ajándékként érkezik.', 'en' => 'The suit’s principle arrives as a raw beginning, a gift.', 'shadow_hu' => 'A kezdet nem folytatódik, vagy túlcsordul formátlanul.', 'shadow_en' => 'The beginning does not continue, or overflows without a form.'],
    '02' => ['hu' => 'Két erő találkozik: választás, pár, egyensúly.', 'en' => 'Two forces meet: a choice, a pair, a balance.', 'shadow_hu' => 'A kettősség döntés nélkül áll, vagy az egyik oldal eltűnik.', 'shadow_en' => 'The pair stands without a decision, or one side disappears.'],
    '03' => ['hu' => 'Az első eredmény, társaság, a növekedés láthatóvá válik.', 'en' => 'A first result, company, growth becoming visible.', 'shadow_hu' => 'A harmadik zavar, vagy az öröm szétesik.', 'shadow_en' => 'The third brings confusion, or the joy comes apart.'],
    '04' => ['hu' => 'Szerkezet, pihenő, a már meglévő megtartása.', 'en' => 'Structure, a pause, holding what is already there.', 'shadow_hu' => 'A tartás bezár, vagy a rend unalommá merevedik.', 'shadow_en' => 'Holding closes in, or order hardens into dullness.'],
    '05' => ['hu' => 'Próba, veszteség, súrlódás, amin át kell menni.', 'en' => 'A trial, a loss, friction that has to be crossed.', 'shadow_hu' => 'A veszteség azonosítja az embert, vagy a harc öncélú.', 'shadow_en' => 'The loss becomes an identity, or the fight exists for itself.'],
    '06' => ['hu' => 'Átmenet, adás, egy korábbi hely elhagyása vagy visszaidézése.', 'en' => 'A passage, a gift, leaving or recalling an earlier place.', 'shadow_hu' => 'A múlt fogva tart, vagy az adás egyenlőtlen.', 'shadow_en' => 'The past keeps hold, or the giving is unequal.'],
    '07' => ['hu' => 'Türelem, stratégia, a sok lehetőség közötti vizsgálat.', 'en' => 'Patience, strategy, testing among many possibilities.', 'shadow_hu' => 'Illúzió, halogatás, vagy a rejtett út csalássá válik.', 'shadow_en' => 'Illusion, delay, or the hidden path turning into deceit.'],
    '08' => ['hu' => 'Mozgás, gyakorlás, a készség, amely dolgozik.', 'en' => 'Movement, practice, a skill that is at work.', 'shadow_hu' => 'Sietés, vagy a korlát, amelyet a félelem épít.', 'shadow_en' => 'Haste, or a limit built by fear.'],
    '09' => ['hu' => 'Majdnem teljes eredmény, a magányos beteljesedés küszöbe.', 'en' => 'A near result, the threshold of a solitary fulfillment.', 'shadow_hu' => 'Aggodalom a beteljesedés előtt, vagy az élvezet bezárása.', 'shadow_en' => 'Worry before fulfillment, or closing the enjoyment in.'],
    '10' => ['hu' => 'A kör lezárása: otthon, teher vagy vég, amelyből új szín kezdődhet.', 'en' => 'The circle closes: a home, a burden, or an end from which another suit can start.', 'shadow_hu' => 'A teljesség teherré vagy végső sebbé válik.', 'shadow_en' => 'Completeness becomes a burden or a final wound.'],
    'page' => ['hu' => 'Tanítvány, hír, a szín első személyes hangja.', 'en' => 'A student, a message, the suit’s first personal voice.', 'shadow_hu' => 'Éretlen hír, vagy a kíváncsiság, amely nem tanul.', 'shadow_en' => 'An unripe message, or curiosity that does not study.'],
    'knight' => ['hu' => 'Úton lévő erő, a szín mozgásban.', 'en' => 'A force on the road, the suit in motion.', 'shadow_hu' => 'Impulzus kormány nélkül, vagy a küldetés, amely elakad.', 'shadow_en' => 'Impulse without a rein, or a quest that stalls.'],
    'queen' => ['hu' => 'A szín belső mestersége, gondoskodó vagy éles jelenlét.', 'en' => 'The suit’s inner mastery, a caring or a sharp presence.', 'shadow_hu' => 'A belső mesterség bezár, vagy a másik helyett érez és dönt.', 'shadow_en' => 'Inner mastery closes in, or feels and decides in place of the other.'],
    'king' => ['hu' => 'A szín külső mestersége, felelős irányítás.', 'en' => 'The suit’s outer mastery, responsible direction.', 'shadow_hu' => 'Hatalom a szín nevében, amely már nem szolgál.', 'shadow_en' => 'Power in the name of the suit that no longer serves.'],
];

function rankKey(string $figureId): string
{
    if (str_ends_with($figureId, '-ace')) {
        return 'ace';
    }
    $part = substr($figureId, (int) strpos($figureId, '-') + 1);

    return $part;
}

function suitKey(string $figureId): string
{
    return explode('-', $figureId, 2)[0];
}

$majorRecords = [];
foreach (App\Support\MajorArcanaMeaningCatalog::entries() as $index => $card) {
    $extra = $majorExtras[$card['figure_id']];
    $text = $card['name_hu'].' ('.$card['name_en'].'). Nagy arkánum '.$index.'. Katalógusjelentés: '.$card['meaning_hu'].'. '.$card['description_hu']
        .' Fény: '.$extra['light_hu'].' Árnyék: '.$extra['shadow_hu'].' Az életben: '.$extra['life_hu']
        .' English: '.$card['meaning_en'].'. '.$card['description_en'].' Light: '.$extra['light_en'].' Shadow: '.$extra['shadow_en'].' In a life: '.$extra['life_en'];
    $majorRecords[] = record($card['figure_id'], 'major-arcana', $text, [
        'number' => $index,
        'name_hu' => $card['name_hu'],
        'name_en' => $card['name_en'],
        'meaning_hu' => $card['meaning_hu'],
        'meaning_en' => $card['meaning_en'],
        'description_hu' => $card['description_hu'],
        'description_en' => $card['description_en'],
        'light_hu' => $extra['light_hu'],
        'shadow_hu' => $extra['shadow_hu'],
        'life_hu' => $extra['life_hu'],
        'light_en' => $extra['light_en'],
        'shadow_en' => $extra['shadow_en'],
        'life_en' => $extra['life_en'],
    ]);
}

$minorRecords = [[
    'id' => 'minor-suits',
    'kind' => 'suit-overview',
    'text' => 'A kis arkánum négy színe a négy elem. Kelyhek: víz, érzelem és kapcsolat. Botok: tűz, akarat és tett. Érmék: föld, munka, pénz és test. Kardok: levegő, gondolat, szó és konfliktus. A lap fénye a katalógusjelentés támogató arca, az árnyéka ugyanannak a témának a túlcsordulása vagy elakadása. A program light értéke a fény, a shadow értéke az árnyék. Cups are water, Wands fire, Pentacles earth, Swords air.',
]];
foreach (App\Support\MinorArcanaMeaningCatalog::entries() as $card) {
    $suit = $suits[suitKey($card['figure_id'])];
    $rank = $ranks[rankKey($card['figure_id'])];
    $text = $card['name_hu'].' ('.$card['name_en'].'). Kis arkánum, '.$suit['hu'].', elem: '.$suit['element_hu'].'. Katalógusjelentés: '.$card['meaning_hu'].'. '.$card['description_hu']
        .' Fény: '.$rank['hu'].' Árnyék: '.$rank['shadow_hu'].' Az életben: '.$suit['life_hu']
        .' English: '.$card['meaning_en'].'. '.$card['description_en'].' Light: '.$rank['en'].' Shadow: '.$rank['shadow_en'].' In a life: '.$suit['life_en'];
    $minorRecords[] = record($card['figure_id'], 'minor-arcana', $text, [
        'suit_hu' => $suit['hu'],
        'suit_en' => $suit['en'],
        'element_hu' => $suit['element_hu'],
        'element_en' => $suit['element_en'],
        'name_hu' => $card['name_hu'],
        'name_en' => $card['name_en'],
        'meaning_hu' => $card['meaning_hu'],
        'meaning_en' => $card['meaning_en'],
        'description_hu' => $card['description_hu'],
        'description_en' => $card['description_en'],
        'light_hu' => $rank['hu'],
        'shadow_hu' => $rank['shadow_hu'],
        'life_hu' => $suit['life_hu'],
        'light_en' => $rank['en'],
        'shadow_en' => $rank['shadow_en'],
        'life_en' => $suit['life_en'],
    ]);
}

$planets = [
    ['id' => 'sun', 'hu' => 'Nap', 'en' => 'Sun', 'nature' => 1, 'element' => 'tűz', 'rules' => ['Oroszlán'], 'exalt' => ['Kos'], 'detriment' => ['Vízöntő'], 'fall' => ['Mérleg'], 'life_hu' => 'Életerő, azonosság, akarat, láthatóság, a tudatos középpont. Apai elv, tekintély, szív.', 'life_en' => 'Vitality, identity, will, visibility, the conscious center. A paternal principle, authority, the heart.', 'light_hu' => 'Meleg jelenlét, tiszta cél, alkotó erő.', 'shadow_hu' => 'Gőg, középpontkényszer, kiégés.', 'light_en' => 'A warm presence, a clear aim, creative force.', 'shadow_en' => 'Pride, a need to be the center, burnout.'],
    ['id' => 'moon', 'hu' => 'Hold', 'en' => 'Moon', 'nature' => 1, 'element' => 'víz', 'rules' => ['Rák'], 'exalt' => ['Bika'], 'detriment' => ['Bak'], 'fall' => ['Skorpió'], 'life_hu' => 'Test, hangulat, otthon, anyai elv, napi szükség, emlékezet, a nép és a megszokás.', 'life_en' => 'Body, mood, home, the maternal principle, daily need, memory, habit.', 'light_hu' => 'Gondoskodás, alkalmazkodó érzés, tápláló ritmus.', 'shadow_hu' => 'Hangulatingás, ragaszkodás, bizonytalan biztonság.', 'light_en' => 'Care, adaptable feeling, a nourishing rhythm.', 'shadow_en' => 'Mood swings, clinging, an unsafe kind of safety.'],
    ['id' => 'mercury', 'hu' => 'Merkúr', 'en' => 'Mercury', 'nature' => 0, 'element' => 'levegő', 'rules' => ['Ikrek', 'Szűz'], 'exalt' => ['Szűz'], 'detriment' => ['Nyilas', 'Halak'], 'fall' => ['Halak'], 'life_hu' => 'Elme, beszéd, kereskedelem, tanulás, testvérek, kéz, ügyintézés. A természet a programban semleges: nem ad előjelet magától.', 'life_en' => 'Mind, speech, trade, study, siblings, the hands, errands. Its program nature is neutral: it adds no sign of its own.', 'light_hu' => 'Világos szó, ügyes közvetítés, tanulékony elme.', 'shadow_hu' => 'Szétszórtság, csűrés-csavarás, ideges sietség.', 'light_en' => 'Clear speech, skilled mediation, a mind that learns.', 'shadow_en' => 'Scattering, twist of words, nervous haste.'],
    ['id' => 'venus', 'hu' => 'Vénusz', 'en' => 'Venus', 'nature' => 1, 'element' => 'föld', 'rules' => ['Bika', 'Mérleg'], 'exalt' => ['Halak'], 'detriment' => ['Kos', 'Skorpió'], 'fall' => ['Szűz'], 'life_hu' => 'Szeretet, ízlés, béke, pénz mint élvezet, művészet, vonzalom. A program természetes eleme a föld.', 'life_en' => 'Love, taste, peace, money as pleasure, art, attraction. The program’s natural element for Venus is earth.', 'light_hu' => 'Harmónia, gyengéd kötés, érték, amely táplál.', 'shadow_hu' => 'Hiúság, kényelemfüggés, a konfliktus kerülése.', 'light_en' => 'Harmony, a tender bond, value that nourishes.', 'shadow_en' => 'Vanity, dependence on comfort, avoiding conflict.'],
    ['id' => 'mars', 'hu' => 'Mars', 'en' => 'Mars', 'nature' => -1, 'element' => 'tűz', 'rules' => ['Kos', 'Skorpió'], 'exalt' => ['Bak'], 'detriment' => ['Bika', 'Mérleg'], 'fall' => ['Rák'], 'life_hu' => 'Bátorság, harag, vágás, szexuális erő, verseny, a határ, amelyet meg kell védeni. A program előjele nehezítő, nem azonos a rosszal.', 'life_en' => 'Courage, anger, a cut, sexual force, contest, a boundary that must be held. Its program sign is challenging, not the same as evil.', 'light_hu' => 'Tiszta kezdeményezés, védelem, elszánt tett.', 'shadow_hu' => 'Agresszió, sietség, seb, amely öncélú.', 'light_en' => 'A clean initiative, defense, a determined act.', 'shadow_en' => 'Aggression, haste, a wound made for its own sake.'],
    ['id' => 'jupiter', 'hu' => 'Jupiter', 'en' => 'Jupiter', 'nature' => 1, 'element' => 'tűz', 'rules' => ['Nyilas', 'Halak'], 'exalt' => ['Rák'], 'detriment' => ['Ikrek', 'Szűz'], 'fall' => ['Bak'], 'life_hu' => 'Növekedés, hit, jog, tanító, utazás, bőkezűség, a jelentés, amely tágít. A program természetes eleme a tűz.', 'life_en' => 'Growth, faith, law, a teacher, travel, generosity, meaning that widens. The program’s natural element is fire.', 'light_hu' => 'Bizalom, lehetőség, bölcs tágasság.', 'shadow_hu' => 'Túlzás, dogma, a mérték nélküli ígéret.', 'light_en' => 'Trust, opportunity, a wise wideness.', 'shadow_en' => 'Excess, dogma, a promise without measure.'],
    ['id' => 'saturn', 'hu' => 'Szaturnusz', 'en' => 'Saturn', 'nature' => -1, 'element' => 'föld', 'rules' => ['Bak', 'Vízöntő'], 'exalt' => ['Mérleg'], 'detriment' => ['Rák', 'Oroszlán'], 'fall' => ['Kos'], 'life_hu' => 'Idő, határ, munka, félelem, csont, öregség, felelősség, a struktúra, amely tart. Nehezítő előjel a programban.', 'life_en' => 'Time, limit, work, fear, bone, age, responsibility, the structure that holds. A challenging sign in the program.', 'light_hu' => 'Kitartás, mesterség, igazságos határ.', 'shadow_hu' => 'Zár, bűntudat, a késleltetés, amely büntet.', 'light_en' => 'Endurance, craft, a fair limit.', 'shadow_en' => 'A lock, guilt, delay used as punishment.'],
    ['id' => 'uranus', 'hu' => 'Uránusz', 'en' => 'Uranus', 'nature' => -1, 'element' => 'levegő', 'rules' => ['Vízöntő'], 'exalt' => [], 'detriment' => [], 'fall' => [], 'life_hu' => 'Törés, találmány, szabadság, váratlan fordulat, a csoport, amely kilép a régi rendből. Modern uralom: Vízöntő. Nehezítő előjel.', 'life_en' => 'A break, invention, freedom, a sudden turn, the group that steps out of an old order. Modern rulership: Aquarius. A challenging sign.', 'light_hu' => 'Ébredés, eredeti megoldás, felszabadító törés.', 'shadow_hu' => 'Öncélú lázadás, sokk, a kötés szétszakítása.', 'light_en' => 'Waking, an original solution, a liberating break.', 'shadow_en' => 'Rebellion for its own sake, shock, tearing a bond.'],
    ['id' => 'neptune', 'hu' => 'Neptunusz', 'en' => 'Neptune', 'nature' => 0, 'element' => 'víz', 'rules' => ['Halak'], 'exalt' => [], 'detriment' => [], 'fall' => [], 'life_hu' => 'Képzelet, köd, együttérzés, művészet, oldódás, vágykép. Semleges előjel. Modern uralom: Halak. Jegybe lépést a program nem eseményként számol.', 'life_en' => 'Imagination, fog, compassion, art, dissolving, an image of longing. Neutral sign. Modern rulership: Pisces. A sign ingress is not an event the program calculates.', 'light_hu' => 'Ihlet, könyörület, a határ lágyulása.', 'shadow_hu' => 'Csalódás, menekülés, a valóság elfolyása.', 'light_en' => 'Inspiration, mercy, a softening of the boundary.', 'shadow_en' => 'Disappointment, escape, reality draining away.'],
    ['id' => 'pluto', 'hu' => 'Plútó', 'en' => 'Pluto', 'nature' => -1, 'element' => 'víz', 'rules' => ['Skorpió'], 'exalt' => [], 'detriment' => [], 'fall' => [], 'life_hu' => 'Kényszer, hatalom, vég és újrakezdés, közös titok, a mély átalakulás. Modern uralom: Skorpió. Nehezítő előjel.', 'life_en' => 'Compulsion, power, an end and a restart, a shared secret, deep transformation. Modern rulership: Scorpio. A challenging sign.', 'light_hu' => 'Tisztító vége valaminek, ami már nem élhet.', 'shadow_hu' => 'Hatalmi harc, rögeszme, a pusztítás, amely nem épít újat.', 'light_en' => 'A cleansing end of what can no longer live.', 'shadow_en' => 'A power struggle, obsession, destruction that builds nothing.'],
];

$planetRecords = [];
foreach ($planets as $planet) {
    $points = $planet['nature'] * 3;
    $sign = $planet['nature'] > 0 ? 'pozitív' : ($planet['nature'] < 0 ? 'negatív' : 'semleges');
    $text = $planet['hu'].' ('.$planet['en'].'). Program-előjel: '.$sign.' ('.$planet['nature'].'), a polaritáspont ezért '.$points.'. Természetes elem a programban: '.$planet['element'].'. Uralkodás: '.implode(', ', $planet['rules']).'.'
        .($planet['exalt'] !== [] ? ' Emelkedés: '.implode(', ', $planet['exalt']).'.' : '')
        .($planet['detriment'] !== [] ? ' Száműzetés: '.implode(', ', $planet['detriment']).'.' : '')
        .($planet['fall'] !== [] ? ' Esés: '.implode(', ', $planet['fall']).'.' : '')
        .' Életben: '.$planet['life_hu'].' Fény: '.$planet['light_hu'].' Árnyék: '.$planet['shadow_hu']
        .' English: '.$planet['life_en'].' Light: '.$planet['light_en'].' Shadow: '.$planet['shadow_en'];
    $planetRecords[] = record($planet['id'], 'planet', $text, $planet + ['polarity_points' => $points]);
}

$signs = [
    ['id' => 'aries', 'hu' => 'Kos', 'en' => 'Aries', 'element' => 'tűz', 'modality' => 'fő', 'polarity' => 'pozitív', 'ruler' => 'Mars', 'life_hu' => 'Kezdés, fej, bátorság, az első lépés, harci kedv.', 'life_en' => 'Starting, the head, courage, the first step, fighting spirit.'],
    ['id' => 'taurus', 'hu' => 'Bika', 'en' => 'Taurus', 'element' => 'föld', 'modality' => 'szilárd', 'polarity' => 'negatív', 'ruler' => 'Vénusz', 'life_hu' => 'Test, érték, kitartás, érzéki biztonság, a megtartott jó.', 'life_en' => 'Body, value, endurance, sensual security, the good that is kept.'],
    ['id' => 'gemini', 'hu' => 'Ikrek', 'en' => 'Gemini', 'element' => 'levegő', 'modality' => 'változó', 'polarity' => 'pozitív', 'ruler' => 'Merkúr', 'life_hu' => 'Szó, testvér, tanulás, két út, a kíváncsi elme.', 'life_en' => 'Speech, a sibling, study, two roads, the curious mind.'],
    ['id' => 'cancer', 'hu' => 'Rák', 'en' => 'Cancer', 'element' => 'víz', 'modality' => 'fő', 'polarity' => 'negatív', 'ruler' => 'Hold', 'life_hu' => 'Otthon, anya, gyökér, védelem, a belső család.', 'life_en' => 'Home, mother, root, protection, the inner family.'],
    ['id' => 'leo', 'hu' => 'Oroszlán', 'en' => 'Leo', 'element' => 'tűz', 'modality' => 'szilárd', 'polarity' => 'pozitív', 'ruler' => 'Nap', 'life_hu' => 'Szív, alkotás, gyermek, méltóság, a látható öröm.', 'life_en' => 'Heart, creation, a child, dignity, visible joy.'],
    ['id' => 'virgo', 'hu' => 'Szűz', 'en' => 'Virgo', 'element' => 'föld', 'modality' => 'változó', 'polarity' => 'negatív', 'ruler' => 'Merkúr', 'life_hu' => 'Mesterség, egészség, rend, szolgálat, a részlet, amely javít.', 'life_en' => 'Craft, health, order, service, the detail that repairs.'],
    ['id' => 'libra', 'hu' => 'Mérleg', 'en' => 'Libra', 'element' => 'levegő', 'modality' => 'fő', 'polarity' => 'pozitív', 'ruler' => 'Vénusz', 'life_hu' => 'Pár, szerződés, igazság, ízlés, a másikkal kötött egyensúly.', 'life_en' => 'A pair, a contract, justice, taste, balance made with another.'],
    ['id' => 'scorpio', 'hu' => 'Skorpió', 'en' => 'Scorpio', 'element' => 'víz', 'modality' => 'szilárd', 'polarity' => 'negatív', 'ruler' => 'Mars, modern uralkodó Plútó', 'life_hu' => 'Mély kötés, válság, közös titok, átalakulás, a nem engedett vágy.', 'life_en' => 'A deep bond, crisis, a shared secret, transformation, desire that is not released.'],
    ['id' => 'sagittarius', 'hu' => 'Nyilas', 'en' => 'Sagittarius', 'element' => 'tűz', 'modality' => 'változó', 'polarity' => 'pozitív', 'ruler' => 'Jupiter', 'life_hu' => 'Hit, távol, tanítás, törvény, a jelentés keresése.', 'life_en' => 'Faith, distance, teaching, law, the search for meaning.'],
    ['id' => 'capricorn', 'hu' => 'Bak', 'en' => 'Capricorn', 'element' => 'föld', 'modality' => 'fő', 'polarity' => 'negatív', 'ruler' => 'Szaturnusz', 'life_hu' => 'Hivatás, idő, teherviselés, tekintély, a lassan épített hely.', 'life_en' => 'Vocation, time, bearing a load, authority, a place built slowly.'],
    ['id' => 'aquarius', 'hu' => 'Vízöntő', 'en' => 'Aquarius', 'element' => 'levegő', 'modality' => 'szilárd', 'polarity' => 'pozitív', 'ruler' => 'Szaturnusz, modern uralkodó Uránusz', 'life_hu' => 'Csoport, eszme, barátság, a jövő, a személyes rendből kilépő közösség.', 'life_en' => 'A group, an idea, friendship, the future, a community stepping out of a private order.'],
    ['id' => 'pisces', 'hu' => 'Halak', 'en' => 'Pisces', 'element' => 'víz', 'modality' => 'változó', 'polarity' => 'negatív', 'ruler' => 'Jupiter, modern uralkodó Neptunusz', 'life_hu' => 'Együttérzés, képzelet, határtalanság, visszavonulás, a kör vége.', 'life_en' => 'Compassion, imagination, boundlessness, retreat, the end of the circle.'],
];
$signRecords = [];
foreach ($signs as $sign) {
    $base = $sign['polarity'] === 'pozitív' ? 3 : -3;
    $planetPoints = $sign['polarity'] === 'pozitív' ? 2 : -2;
    $text = $sign['hu'].' ('.$sign['en'].'). Elem: '.$sign['element'].'. Minőség: '.$sign['modality'].'. Polaritás: '.$sign['polarity'].($sign['polarity'] === 'pozitív' ? ' (aktív)' : ' (befogadó)').'. A jegy saját pontja a jegytáblában '.$base.'. Ha egy bolygó ebben a jegyben áll, a jegy pontja a bolygónál '.$planetPoints.'. Uralkodó: '.$sign['ruler'].'. Életben: '.$sign['life_hu'].' English: '.$sign['life_en'].' A negatív polaritás befogadó minőséget jelent, nem rosszat.';
    $signRecords[] = record($sign['id'], 'sign', $text, $sign + [
        'sign_base_points' => $base,
        'planet_in_sign_points' => $planetPoints,
    ]);
}

$houses = [
    [1, 'fő', 'Kos', 'Én, test, megjelenés, az első benyomás.', 'Self, body, appearance, the first impression.'],
    [2, 'szilárd', 'Bika', 'Pénz, birtok, tehetség, amit meg lehet tartani.', 'Money, possessions, talent, what can be kept.'],
    [3, 'változó', 'Ikrek', 'Testvér, beszéd, rövid út, tanulás, szomszédság.', 'Siblings, speech, a short journey, study, the neighborhood.'],
    [4, 'fő', 'Rák', 'Otthon, gyökér, egyik szülő, a nap vége, a belső alap.', 'Home, root, one parent, the end of the day, the inner foundation.'],
    [5, 'szilárd', 'Oroszlán', 'Gyermek, játék, szerelem, alkotás, kockázat.', 'Children, play, romance, creation, risk.'],
    [6, 'változó', 'Szűz', 'Munka, egészség, szolgálat, napi rend, kis állatok.', 'Work, health, service, daily order, small animals.'],
    [7, 'fő', 'Mérleg', 'Társ, házasság, nyílt ellenfél, szerződés. A DESC háza.', 'Partner, marriage, an open opponent, a contract. The house of the DESC.'],
    [8, 'szilárd', 'Skorpió', 'Közös vagyon, válság, szexualitás, adósság, átalakulás.', 'Shared resources, crisis, sexuality, debt, transformation.'],
    [9, 'változó', 'Nyilas', 'Hit, távoli út, jog, felső tanulás, idegen föld.', 'Faith, a far journey, law, higher study, a foreign land.'],
    [10, 'fő', 'Bak', 'Hivatás, státusz, nyilvános szerep, a másik szülő. Az MC háza.', 'Vocation, status, a public role, the other parent. The house of the MC.'],
    [11, 'szilárd', 'Vízöntő', 'Barátok, csoport, remény, a jövő terve.', 'Friends, a group, hope, a plan for the future.'],
    [12, 'változó', 'Halak', 'Visszavonulás, rejtett ellenség, veszteség, magány, a láthatatlan.', 'Retreat, a hidden enemy, loss, solitude, what is unseen.'],
];
$houseRecords = [];
foreach ($houses as [$number, $modality, $sign, $hu, $en]) {
    $kind = in_array($number, [1, 4, 7, 10], true) ? 'sarok' : (in_array($number, [2, 5, 8, 11], true) ? 'követő' : 'hanyatló');
    $text = $number.'. ház. Természetes jegy: '.$sign.'. Minőség: '.$modality.'. Házfajta: '.$kind.'. Életben: '.$hu.' English: '.$en.' A program a házakat Placidus rendszerben számolja. A ház a benne álló bolygó életterülete, nem külön jó vagy rossz hely.';
    $houseRecords[] = record('house-'.$number, 'house', $text, [
        'number' => $number,
        'natural_sign_hu' => $sign,
        'modality_hu' => $modality,
        'class_hu' => $kind,
        'life_hu' => $hu,
        'life_en' => $en,
    ]);
}

$aspects = [
    ['id' => 'conjunction', 'hu' => 'együttállás', 'angle' => 0, 'orb' => 8, 'points' => 0, 'tone' => 'a két erő összeolvad, előjel nélkül', 'hu_text' => 'Két téma egy helyen dolgozik. A program 0 pontot ad, és a kész osztályozásban a nulla pozitív. Nem automatikusan jó és nem automatikusan rossz: a két résztvevő természete dönti el.', 'en_text' => 'Two themes work in one place. The program gives 0 points, and a zero total is classed positive. It is not automatically good or bad: the two bodies decide it.'],
    ['id' => 'sextile', 'hu' => 'szextil', 'angle' => 60, 'orb' => 4, 'points' => 1, 'tone' => 'könnyű lehetőség', 'hu_text' => 'Nyitott alkalom, amely lépést kér. Támogató fényszög, +1 pont.', 'en_text' => 'An open chance that asks for a step. A supportive aspect, +1 point.'],
    ['id' => 'square', 'hu' => 'kvadrát', 'angle' => 90, 'orb' => 6, 'points' => -1, 'tone' => 'súrlódás, tettkényszer', 'hu_text' => 'Belső vagy külső feszültség, amely cselekvést követel. Nehezítő fényszög, −1 pont.', 'en_text' => 'Inner or outer tension that demands action. A challenging aspect, −1 point.'],
    ['id' => 'trine', 'hu' => 'trigon', 'angle' => 120, 'orb' => 6, 'points' => 1, 'tone' => 'áramló támogatás', 'hu_text' => 'Azonos elem könnyű áramlása. Támogató, +1 pont. Vigyázat: a könnyűség el is altathat.', 'en_text' => 'An easy flow of the same element. Supportive, +1 point. Ease can also make a person passive.'],
    ['id' => 'quincunx', 'hu' => 'kvinkunx', 'angle' => 150, 'orb' => 3, 'points' => -1, 'tone' => 'illesztési kényszer', 'hu_text' => 'Két életterület, amely nem érti egymást, és igazítást kér. Nehezítő, −1 pont. Szűk, 3 fokos orbis.', 'en_text' => 'Two life areas that do not understand each other and need adjustment. Challenging, −1 point. A tight 3 degree orb.'],
    ['id' => 'opposition', 'hu' => 'oppozíció', 'angle' => 180, 'orb' => 8, 'points' => -1, 'tone' => 'szembeállás, a másik tükre', 'hu_text' => 'Két pólus, gyakran kapcsolatban vagy választásban. Nehezítő, −1 pont, de tudatosít.', 'en_text' => 'Two poles, often in a relationship or a choice. Challenging, −1 point, and it makes the theme conscious.'],
];
$aspectRecords = [];
foreach ($aspects as $aspect) {
    $text = $aspect['hu'].' ('.$aspect['id'].'), '.$aspect['angle'].' fok, orbis '.$aspect['orb'].' fok, pont '.$aspect['points'].'. '.$aspect['tone'].'. '.$aspect['hu_text'].' English: '.$aspect['en_text'].' A kompakt sor angol nevet használ, és minden pár csak egyszer szerepel, a korábbi égitest felé.';
    $aspectRecords[] = record($aspect['id'], 'aspect', $text, $aspect);
}

$points = [
    ['id' => 'asc', 'in_row' => true, 'hu' => 'ASC, aszcendens', 'text' => 'ASC, az aszcendens. Az 1. ház csúcsa, a keleti horizont. A személy megjelenése, teste, az a mód, ahogyan a világ elé lép. A kompakt sorban benne van, R/N nélkül. A DESC az ASC + 180 fok, a 7. ház.'],
    ['id' => 'desc', 'in_row' => true, 'hu' => 'DESC, deszcendens', 'text' => 'DESC, a deszcendens. Az ASC szemben, mindig ASC + 180 fok, a program a 7. házba teszi. A társ, a nyílt másik, a szerződés. A kompakt sorban benne van, R/N nélkül. Nem önálló égitest.'],
    ['id' => 'mc', 'in_row' => false, 'hu' => 'MC, medium coeli', 'text' => 'MC, a középég, a 10. ház csúcsa. Hivatás, nyilvános név, a dél iránya. A program kiszámolja, de a kompakt égi sorba most nincs felvéve. Az IC a szemben lévő 4. házcsúcs, otthon és gyökér.'],
    ['id' => 'fortune', 'in_row' => true, 'hu' => 'Fortune, szerencsepont', 'text' => 'Fortune, a szerencsepont. A program így számolja: ha a Nap háza 7 vagy nagyobb, nappali képlet, ASC + Hold − Nap. Különben éjszakai, ASC + Nap − Hold. A kompakt sorban benne van, R/N nélkül. A test, a hétköznapi szerencse, a Hold és a Nap találkozása az aszcendensen. Nem a pénz önálló pontja.'],
    ['id' => 'nodes', 'in_row' => false, 'hu' => 'holdcsomópontok', 'text' => 'A felszálló, északi holdcsomópont (True Node) és a leszálló, déli csomópont (True Node + 180 fok) ki van számítva, de a kompakt sorba nincs felvéve. Északon a fejlesztendő irány, délen a már ismert, könnyen ismételt minta. Nem jó és rossz, hanem ismert és tanulandó.'],
    ['id' => 'lilith', 'in_row' => false, 'hu' => 'Mean Lilith', 'text' => 'A Mean Lilith, a fekete hold középértéke ki van számítva, de a kompakt sorba nincs felvéve. A száműzött vágy, a nyers női árnyék, ami a kontroll alól kilép. Csak akkor értelmezd, ha a kapott adatban szerepel.'],
];
$pointRecords = [];
foreach ($points as $point) {
    $pointRecords[] = record($point['id'], 'point', $point['text'].' English names in the chart row: ASC, DESC, Fortune. MC, the nodes and Lilith stay out of that row unless a later message includes them.', $point);
}

$stars = [
    ['Aldebaran', 'Bika szeme', 'becsület, őrzés, a gyors indulat veszélye'],
    ['Regulus', 'Oroszlán szíve', 'vezetés, hírnév, a gőg miatti hirtelen esés'],
    ['Spica', 'Szűz keze', 'adomány, mesterség, oltalom'],
    ['Antares', 'Skorpió szíve', 'intenzitás, harc, a véglet'],
    ['Fomalhaut', 'déli őrző', 'eszmény, magányos látomás, költészet'],
    ['Sirius', 'Nagy Kutya', 'fény, hír, forró siker, amely kockázattal jár'],
    ['Algol', 'Perszeusz', 'éles erő, félelem, levágott fej; nem átok, hanem intenzitás, óvatosan'],
    ['Alcyone', 'Plejádok', 'látomás, gyász, a csoportból kiváló szem'],
    ['Castor', 'Ikrek egyike', 'értelem, írás, kettősség'],
    ['Pollux', 'Ikrek másika', 'bátorság, verseny, kézműves erő'],
    ['Procyon', 'Kis Kutya', 'gyors emelkedés, élénk elme, rövid gyújtás'],
    ['Betelgeuse', 'Orion válla', 'hírnév, harci siker, hirtelen fordulat'],
    ['Rigel', 'Orion lába', 'tanítás, rang, technikai tudás'],
    ['Bellatrix', 'Orion', 'gyors tett, küzdelem, harcos készenlét'],
    ['Alnilam', 'Orion öve', 'kitartó munka általi hír, a középen álló feladat'],
    ['Alphard', 'Hydra', 'magányos érzelem, erős belső hő'],
    ['Vega', 'Lant', 'kisugárzás, művészet, jelenlét'],
    ['Deneb', 'Hattyú', 'magas cél, út, kitartó erő'],
    ['Altair', 'Sas', 'merészség, sebesség, ideg'],
    ['Capella', 'Szekeres', 'tanulás, nyilvános hang, a tudásból szerzett érték'],
];
$starRecords = [];
foreach ($stars as [$name, $place, $meaning]) {
    $text = $name.' ('.$place.'). Hagyományos olvasat: '.$meaning.'. A program csak együttállást jelez, 1 fokos orbisszal, és a csillag nem nyit külön sort: a bolygó vagy pont fényszögsorában áll, conjunction '.$name.' formában. Ha nincs a kapott sorban, ne említsd.';
    $starRecords[] = record(strtolower($name), 'fixed-star', $text, [
        'name' => $name,
        'place_hu' => $place,
        'meaning_hu' => $meaning,
        'orb' => 1.0,
        'only' => 'conjunction',
    ]);
}

$framework = [
    record('four-elements', 'framework', 'Négy elem. Tűz: akarat, lelkesedés, sietség; jegyek Kos, Oroszlán, Nyilas; tarot szín Botok. Föld: test, munka, pénz, türelem; Bika, Szűz, Bak; Érmék. Levegő: gondolat, szó, kapcsolat, távolság; Ikrek, Mérleg, Vízöntő; Kardok. Víz: érzés, emlékezet, kötés, hangulat; Rák, Skorpió, Halak; Kelyhek. Az elem nem jó vagy rossz. A képletben az erős elem azt mutatja, milyen nyelven válaszol az ember. Fire is will, earth is body and work, air is thought, water is feeling.', [
        'elements' => ['tűz', 'föld', 'levegő', 'víz'],
    ]),
    record('three-modalities', 'framework', 'Három minőség. Fő (kardinális): kezd, indít; Kos, Rák, Mérleg, Bak. Szilárd: megtart, rögzít; Bika, Oroszlán, Skorpió, Vízöntő. Változó: alkalmazkodik, közvetít; Ikrek, Szűz, Nyilas, Halak. A fő minőség indít, a szilárd kitart, a változó átvezet. Cardinal starts, fixed holds, mutable adapts.', [
        'modalities' => ['fő', 'szilárd', 'változó'],
    ]),
    record('polarity', 'framework', 'Polaritás a programban. A jegy pozitív (aktív) vagy negatív (befogadó). A negatív nem rossz. Pozitív jegyek: Kos, Ikrek, Oroszlán, Mérleg, Nyilas, Vízöntő. Negatív jegyek: Bika, Rák, Szűz, Skorpió, Bak, Halak. Bolygó előjele, szorozva hárommal: Nap +3, Hold +3, Merkúr 0, Vénusz +3, Mars −3, Jupiter +3, Szaturnusz −3, Uránusz −3, Neptunusz 0, Plútó −3. A jegy a bolygónál +2 vagy −2. A jegy saját táblája +3 vagy −3, plusz a benne álló bolygók pontjai. Szextil és trigon +1, kvadrát, kvinkunx és oppozíció −1, együttállás 0. A bolygó vagy jegy összegénél a 0 pozitív osztály. Csak a nulla alatti összeg negatív. Positive means active, negative means receptive. A total of zero is classed positive.', []),
    record('chart-lines', 'framework', 'A kapott égi sor a program számítása, nem találgatás. Sorrend: Sun, Moon, Mercury, Venus, Mars, Jupiter, Saturn, Uranus, Neptune, Pluto, ASC, DESC, Fortune. Pozíció: Név,Jegy,fok egy tizedessel,ház, és a bolygóknál R vagy N. Az ASC, DESC és Fortune sorában nincs R/N. A fényszög sor csak a listában korábbi test felé mutat, ezért egy pár egyszer szerepel. Angol nevek: conjunction, sextile, square, trine, quincunx, opposition. Az állócsillag nem nyit sort. A user: none és a relation: none hiányzó adat, nem üres személy. A now a kérdezés pillanata. A születési pillanat a születési helyet használja, ha van; minden más pillanat a tartózkodási helyet, különben az oldal alap koordinátáit. Ne írj olyan helyzetet, amely nincs a sorban.', []),
    record('light-shadow', 'framework', 'A lap light értéke a támogató arc, a shadow az óvatosság, az árnyék, a kerülendő túlzás. Ahol a kirakás light-shadow értéke nem az LLM-é, a kapott sorslenyomatot használd, és ne írd felül. Ahol igen, a kész válasz light_shadow tömbjében slot, row, place és value legyen, a value csak light vagy shadow. A 0 polaritás light. Light is the supportive face. Shadow is caution and excess. A zero polarity is light.', []),
];

$systemHu = <<<'TXT'
Ez a rendszerprompt a weblap és közted lévő adatprotokoll. A hang, a hossz és a válasz íve az alapprompt. A lapok, az elemek, a minőségek, a bolygók, a jegyek, a házak, a fényszögek, a nevezetes pontok és az állócsillagok jelentése a tudástárban van. A kirakás saját utasítása minden üzenet ## KIRAKASPROMPT részében érkezik. Ne keress kirakásszöveget azonosító alapján, és ne a tudástárból vedd elő.

Egy kirakás egy szál. A pontosítás, a bővebb kifejtés és az elfogadott adatpótlás ugyanebben a szálban marad. Nincs rejtett memória a szálon kívül.

Minden válaszod egyetlen JSON objektum. Ne tegyél köré markdownot, és ne írj a JSON-on kívül szöveget.

Kész olvasat:
{"status":"answer","question_type":"...","spread_type":"...","text":"...","light_shadow":[{"slot":1,"row":1,"place":1,"value":"light"}]}

Ha az üzenet megengedi, és még nem kaptál ## ADAT sort, egyszer kérhetsz pontosítást ugyanabban a válaszban. A text ilyenkor is kész olvasat: ezt látja a kérdező, ha nem pontosít.
{"status":"answer","question_type":"...","spread_type":"...","text":"...","need":"birth","requests":[]}

A need csak birth, relation vagy projection.
birth: a kérdező születési ideje hiányzik. A program űrlapot mutat, és a szöveget addig rejti.
relation: egy másik személy születési ideje hiányzik. A szöveget a program szintén rejti, amíg a kérdező nem dönt.
projection: a program számol. A kérés a requests tömbben legyen. A szöveget a kérdező látja, és csak a küldést hagyja jóvá.
Ha az üzenet azt mondja, hogy ne kérj égi adatot, a need mezőt hagyd el.

Projection kérés, legfeljebb négy egyszerre. Az event csak aspect, house vagy retrograde. Jegybe lépést ne kérj.
{"id":"1","kind":"projection","body":"Venus","event":"aspect","aspect":"sextile","with":"Saturn","from":"2026-11-01T00:00:00Z","until":"2026-12-01T00:00:00Z"}
{"id":"2","kind":"projection","body":"Mars","event":"house","house":10,"from":"2026-11-01T00:00:00Z","until":"2026-12-01T00:00:00Z"}
{"id":"3","kind":"projection","body":"Mercury","event":"retrograde","from":"2026-11-01T00:00:00Z","until":"2027-01-01T00:00:00Z"}
Az időszak legfeljebb 120 nap. A válasz ablakokat ad, nem igen-nem ítéletet. A ## ADAT után már ne kérj új adatot.

A light_shadow csak annál a lapnál kell, ahol az üzenet light-shadow: igen. value csak light vagy shadow. A slot a lap sorszáma 1-től. A 0 polaritás light. Ahol light-shadow: nem, a kapott sorslenyomatot használd.

A question_type rövid címke. A spread_type a felsorolt kategória, ha az üzenet ad listát: BINARY, CHOICE, TIMING, EXPLORATORY, FORECAST, ANALYSIS, RELATION, N/A. A dimension a felsorolt életterület, ha az üzenet ad listát: REL, SOC, CAR, FIN, HLT, LEG, SPL, N/A. N/A nem besorolható.

Az égi sor a program számítása. Sorrend: Nap, Hold, Merkúr, Vénusz, Mars, Jupiter, Szaturnusz, Uránusz, Neptunusz, Plútó, ASC, DESC, Fortune. Fok egy tizedesig. R vagy N csak a bolygóknál. A fényszög egy párja egyszer szerepel. Az állócsillag csak együttállásként, azon a testen, amelyhez 1 fokon belül esik. A user: none és a relation: none hiány. Ne találj ki helyzetet, fényszöget, csillagot vagy lapot, amely nincs a kapott adatokban.
TXT;

$systemEn = <<<'TXT'
This system prompt is the data protocol between you and the website. Voice, length and the shape of the answer live in the base prompt. Card meanings, elements, modalities, planets, signs, houses, aspects, notable points and fixed stars live in the knowledge store. The spread’s own instruction arrives in every message under ## SPREAD. Do not look a spread up by id, and do not fetch it from the store.

One spread is one thread. A clarification, a deeper expansion and an accepted data offer stay in that thread. There is no memory outside it.

Every reply is one JSON object. No markdown fence and no text outside the JSON.

A finished reading:
{"status":"answer","question_type":"...","spread_type":"...","text":"...","light_shadow":[{"slot":1,"row":1,"place":1,"value":"light"}]}

When the message allows it, and you have not yet received a ## DATA line, you may ask once, inside the same reply. The text is still a finished reading: it is what the seeker sees if they decline.
{"status":"answer","question_type":"...","spread_type":"...","text":"...","need":"birth","requests":[]}

need is only birth, relation or projection.
birth: the seeker’s birth time is missing. The site shows a form and hides the text until they decide.
relation: another person’s birth time is missing. The site hides the text the same way.
projection: the program calculates it. Put the request in requests. The seeker sees the text and only approves sending it.
If the message says not to request sky data, omit need.

A projection request, at most four at once. event is only aspect, house or retrograde. Do not request a sign ingress.
{"id":"1","kind":"projection","body":"Venus","event":"aspect","aspect":"sextile","with":"Saturn","from":"2026-11-01T00:00:00Z","until":"2026-12-01T00:00:00Z"}
{"id":"2","kind":"projection","body":"Mars","event":"house","house":10,"from":"2026-11-01T00:00:00Z","until":"2026-12-01T00:00:00Z"}
{"id":"3","kind":"projection","body":"Mercury","event":"retrograde","from":"2026-11-01T00:00:00Z","until":"2027-01-01T00:00:00Z"}
The window is at most 120 days. The reply is time windows, not a yes or no. After ## DATA, do not ask for more data.

light_shadow is required only for a card whose message says light-shadow: yes. value is only light or shadow. slot is the card number from 1. A polarity of 0 is light. Where the message says light-shadow: no, keep the fate imprint you were given.

question_type is a short label. spread_type is one of the listed categories when the message gives them: BINARY, CHOICE, TIMING, EXPLORATORY, FORECAST, ANALYSIS, RELATION, N/A. dimension is one of the listed life areas when the message gives them: REL, SOC, CAR, FIN, HLT, LEG, SPL, N/A. N/A means unclassified.

The sky lines are the program’s calculation. Order: Sun, Moon, Mercury, Venus, Mars, Jupiter, Saturn, Uranus, Neptune, Pluto, ASC, DESC, Fortune. Degree to one decimal. R or N only on planets. Each aspect pair appears once. A fixed star is only a conjunction, on the body it touches within 1 degree. user: none and relation: none mean the data is missing. Do not invent a position, aspect, star or card that is not in the supplied data.
TXT;

$baseHu = <<<'TXT'
Te az AstroTarot értelmezője vagy. Meleg, világos magyar hangon beszélsz. A jelölések jelentését a tudástárból veszed. A konkrét eget és a húzott lapokat csak a kapott üzenetből.

Ne használj # címsorokat, számozott szakaszcímeket vagy felsorolást.
Az első olvasat egyetlen folyó szöveg, legfeljebb 10–12 mondat.
Az ív:
1) Ha van égi segítő vagy égi sor, említsd a nevét, és 1–2 kapott adatát a jelentésével. Ha nincs égi adat, ezt a lépést hagyd ki.
2) Mondd a húzott lap katalógusnevét és rövid jelentését, majd kösd a helyzethez.
3) Válaszolj a kérdésre. A light a támogató hangsúly, a shadow az óvatosság és a kerülendő túlzás.

Ha a kérdés értelmetlen karakterhalmaz, billentyűzúgás vagy nem emberi szöveg, ne olvass tarotot és ne kérj adatot. Írd: „A Mestered a kérdést nem tudja értelmezni, de küld egy verset, amire szerinte szükséged van:” Utána egy rövid magyar vers, 4–12 sor, magyarázat nélkül.

Pontosító forduló: a korábbi olvasat fényében válaszolj, legfeljebb 8–10 mondat. Ne kezdj új húzást.
Bővebb kifejtés: menj mélyebbre, legfeljebb 14–16 gyakorlati mondat. Ne ismételd szó szerint az előző szöveget.
Az adatpótlás utáni válasz a ## ADAT sorából épüljön, és ne kérjen új adatot.

Ne ígérj orvosi, jogi vagy pénzügyi bizonyosságot. A negatív polaritás befogadó minőséget jelent, nem ítéletet az emberről.
TXT;

$baseEn = <<<'TXT'
You are the AstroTarot interpreter. Speak in warm, clear English. Take the meanings of the symbols from the knowledge store. Take the actual sky and the drawn cards only from the message you received.

Do not use # headings, numbered section titles, or bullet lists.
The first reading is one flowing text of at most 10–12 sentences.
The arc:
1) If a celestial helper or a sky line is present, name it and mention 1–2 supplied facts with their meaning. If there is no sky data, skip this step.
2) Name the drawn card by its catalog name and short meaning, then bind it to the situation.
3) Answer the question. Light is the supportive emphasis. Shadow is caution and the excess to avoid.

If the question is gibberish, keyboard mash, or not human language, do not read the cards and do not request data. Write: “Your Master cannot interpret the question, but sends a poem they feel you need:” Then a short English poem of 4–12 lines, with no explanation.

A clarification turn answers in the light of the previous reading, in at most 8–10 sentences. Do not start a new draw.
An expansion goes deeper, in at most 14–16 practical sentences. Do not repeat the previous text verbatim.
The reply after supplied data is built from the ## DATA lines, and it does not ask for more data.

Do not promise medical, legal, or financial certainty. Negative polarity means a receptive quality, not a verdict on the person.
TXT;

$meta = [
    'name' => 'AstroTarot agent knowledge',
    'version' => '2026-11-01',
    'use' => 'A rendszer- és az alapprompt az agent utasítása, nyelvenként egy agent. A többi fájl a közös tudástár. A kirakások szövege nincs itt: azt az üzenet küldi a kérdéssel. Ezek a fájlok nem élő efemerida.',
];

writeJson($dir.'/00-index.json', $meta + [
    'files' => [
        '01-prompts.json' => 'Agentutasítás. Nem a vektoros tudástárba való, hanem a rendszer- és az alapprompt mezőbe.',
        '02-tarot-major-arcana.json' => '22 nagy arkánum, a program katalógusjelentésével.',
        '03-tarot-minor-arcana.json' => '56 kis arkánum és a négy szín.',
        '04-astrology-framework.json' => 'Négy elem, három minőség, polaritáspontok, a kompakt égi sor olvasata.',
        '05-astrology-planets.json' => 'Tíz bolygó: előjel, elem, uralom, életterület.',
        '06-astrology-signs.json' => 'Tizenkét jegy.',
        '07-astrology-houses.json' => 'Tizenkét ház.',
        '08-astrology-aspects.json' => 'Hat fényszög, a program orbisával és pontjával.',
        '09-astrology-points.json' => 'ASC, DESC, MC, Fortune, csomópontok, Lilith.',
        '10-astrology-fixed-stars.json' => 'A húsz állócsillag, amelyet a program 1 fokon belül jelez.',
    ],
]);

writeJson($dir.'/01-prompts.json', [
    'id' => 'agent-prompts',
    'version' => '2026-11-01',
    'note' => 'A magyar agent utasítása a system_hu és a base_hu. Az angol agenté a system_en és a base_en. A lap- és égi jelentés a többi fájlban van. A kirakáspromptot nem ide, hanem a kérdés mellé kell tenni.',
    'system_hu' => $systemHu,
    'system_en' => $systemEn,
    'base_hu' => $baseHu,
    'base_en' => $baseEn,
]);

writeJson($dir.'/02-tarot-major-arcana.json', [
    'id' => 'tarot-major-arcana',
    'source' => 'App\\Support\\MajorArcanaMeaningCatalog',
    'records' => $majorRecords,
]);
writeJson($dir.'/03-tarot-minor-arcana.json', [
    'id' => 'tarot-minor-arcana',
    'source' => 'App\\Support\\MinorArcanaMeaningCatalog',
    'records' => $minorRecords,
]);
writeJson($dir.'/04-astrology-framework.json', ['id' => 'astrology-framework', 'records' => $framework]);
writeJson($dir.'/05-astrology-planets.json', ['id' => 'astrology-planets', 'records' => $planetRecords]);
writeJson($dir.'/06-astrology-signs.json', ['id' => 'astrology-signs', 'records' => $signRecords]);
writeJson($dir.'/07-astrology-houses.json', ['id' => 'astrology-houses', 'system' => 'Placidus', 'records' => $houseRecords]);
writeJson($dir.'/08-astrology-aspects.json', ['id' => 'astrology-aspects', 'records' => $aspectRecords]);
writeJson($dir.'/09-astrology-points.json', ['id' => 'astrology-points', 'records' => $pointRecords]);
writeJson($dir.'/10-astrology-fixed-stars.json', [
    'id' => 'astrology-fixed-stars',
    'rule' => 'Only a conjunction within 1 degree. The star does not open a row.',
    'records' => $starRecords,
]);

echo "wrote knowledge files\n";
echo 'major '.count($majorRecords)."\n";
echo 'minor '.count($minorRecords)."\n";
