# Asztrológiai pontozó – PHP 8.1+

Szabályalapú algoritmus, amely objektumok jegyhelyzetéből kiszámítja az
elem-, minőség- és pozitív/negatív dominanciát. A megadott hosszúságokból a
fő fényszögeket is automatikusan felismeri.

## Bemenet

Az `analyze()` rekordlistát vár. Magyar és angol mezőnevek is használhatók:

```php
$records = [
    ['objektum' => 'Nap', 'jegy' => 'Rák', 'fok' => 27.0, 'retrograd' => false],
    ['object' => 'Mars', 'sign' => 'Nyilas', 'degree' => 11.4, 'retrograde' => false],
    ['objektum' => 'Merkúr', 'jegy' => 'Oroszlán', 'fok' => 5.2, 'retrograd' => true],
    ['objektum' => 'ASC', 'jegy' => 'Mérleg', 'fok' => 18.0],
];

$engine = new AstroScoringEngine(0.5);
$result = $engine->analyze($records);
```

Kötelező mezők: objektum, jegy, fok. A fok mindig a jegyen belüli `0 <= fok < 30` érték.

Támogatott nevezetes pontok: ASC, MC, Vertex, Felszálló és Leszálló
Holdcsomó, Lilith. Ezek ugyanúgy részt vesznek a fényszögszámításban, de
szűkebb orbot kapnak.

## Pontozási elv

- Minden jegyhelyzet 1 pontot ad a jegy elemének és minőségének.
- Ha a bolygó természetes eleme megegyezik a jegy elemével: +1 pont.
- Nap és Hold: +1 kiemelési pont.
- ASC: +2; MC: +1,5; Holdcsomók és Vertex: +0,5.
- Uralmi vagy emelt helyzet növeli a hangsúlyt és a pozitív értéket.
- Rangvesztés és esés a negatív értéket növeli.
- Retrográd objektum minden saját hozzájárulása 50%-kal számít.
- A szextil és trigon pozitív, a kvadrát, oppozíció és kvinkunx negatív.
- Az együttállás jellege a résztvevő objektumok természetétől függ.
- A pontosabb fényszög erősebb; a hatás lineárisan csökken az orb széléig.
- Nap/Hold esetén az orb 20%-kal tágabb, nevezetes pontnál 25%-kal szűkebb.

Példák a kívánt logikára:

- általános objektum tüzes jegyben → 1 tűzpont;
- Mars tüzes jegyben → 2 tűzpont;
- Nap tüzes jegyben → 3 tűzpont;
- retrográd Mars tüzes jegyben → 1 tűzpont.

## Fontos fogalmi különbség

A `jegy_polaritas` a klasszikus aktív/befogadó felosztás:

- tűz és levegő: pozitív (aktív);
- föld és víz: negatív (befogadó).

Ez nem jó–rossz értékelés. A `pozitiv_negativ_besorolas` ezzel szemben az
uralmi helyzeteket, a bolygó természetét és a fényszögeket összegző
támogató/feszült minőség.

## Kimenet

```php
[
  'records' => [
    [
      // eredeti mezők, továbbá:
      'elem' => 'víz',
      'elem_ertek' => 2.0,
      'minoseg' => 'fő',
      'minoseg_ertek' => 2.0,
      'jegy_polaritas' => 'negatív (befogadó)',
      'pozitiv_pont' => 1.25,
      'negativ_pont' => 0.0,
      'pozitiv_negativ_besorolas' => 'pozitív',
      'pozitiv_negativ_ertek' => 1.25,
      'magyarazat' => '...'
    ]
  ],
  'aspects' => [/* felismert fényszögek */],
  'summary' => [
    'elem_dominancia' => ['nev' => 'víz', 'ertek' => 9],
    'minoseg_dominancia' => ['nev' => 'fő', 'ertek' => 8],
    'pozitiv_negativ' => ['nev' => 'pozitív', 'ertek' => 6]
  ]
]
```

A kért összesített értékek egész számok. A döntéshez használt pontosabb
nyers értékek is megmaradnak a dominanciarekordokban.

## Futtatás

```bash
php example.php
```

Az eredmény formázott JSON-ként jelenik meg. Webes API-ban ugyanazt a
`$result` tömböt közvetlenül lehet `json_encode()` segítségével visszaadni.

## Tarot-helyek determinisztikus pozitív/negatív besorolása

A `TarotPositionPolarity.php` az asztrológiai számítás eredményéből minden
tarot-helyhez egyértelmű, előjeles értéket rendel:

```php
require 'AstroScoringEngine.php';
require 'TarotPositionPolarity.php';

$astro = (new AstroScoringEngine(0.5))->analyze($records);
$tarot = (new TarotPositionPolarity(0.02))->assign($astro);
```

Az alapértelmezett három hely:

1. **A jelenlegi helyzet lényege** – Nap, Hold, ASC.
2. **A ható erő vagy akadály** – Szaturnusz, Mars, Plútó, Uránusz.
3. **Tanács és várható irány** – Jupiter, Vénusz, Nap, MC.

Minden hely nyers értékének összetevői:

- 35% a teljes képlet pozitív/negatív egyenlege;
- 30% a helyhez tartozó kiemelt objektumok állapota;
- 20% a kiemelt objektumok fényszögeinek jellege és erőssége;
- 15% a képlet súlyozott ekliptikai fázisa;
- alapértelmezésben +2 százalékpont pozitív eltolás.

Minden tarot-helyet önállóan osztályozunk: a nulla vagy annál nagyobb nyers
érték pozitív, a nulla alatti negatív. Nincs helyek közötti rangsor vagy előre
kikényszerített darabszám, ezért három kártyából 0, 1, 2 vagy 3 is lehet
pozitív — így a három pozitív és a három negatív eredmény is lehetséges.

Az algoritmus nem használ véletlenszámot vagy aktuális időt, ezért ugyanarra
a képletre és ugyanarra a helyprofilra mindig ugyanazt az eredményt adja. A
kicsi pozitív eltolás nem egyetlen kirakás arányát szabályozza: sok különböző,
kiegyenlített képleten célozza a körülbelül 52–55%-os pozitív arányt.

Példakimenet:

```php
[
  'positions' => [
    [
      'hely' => 1,
      'nev' => 'A jelenlegi helyzet lényege',
      'besorolas' => 'pozitív',
      'ertek' => 18,
      'erosseg' => 18,
      'nyers_asztrologiai_ertek' => 18,
      'reszertekek' => [/* teljes képlet, objektumok, fényszögek... */],
      'magyarazat' => '...'
    ]
  ],
  'summary' => [
    'pozitiv_helyek' => 1,
    'negativ_helyek' => 2,
    'arany' => '1:2',
    'celzott_hosszu_tavu_arany' => 'kb. 52–55% pozitív',
    'keplet_azonosito' => '...'
  ]
]
```

Egyedi kirakáshoz saját helyprofilok is átadhatók az `assign()` második
paraméterében. A `focus` tömbben objektumnevek és súlyok szerepelnek.

## Finomhangolás

A súlyok az `AstroScoringEngine.php` elején található konfigurációs tömbökben
vannak: természetes elemek, kiemelési bónuszok, bolygójelleg, méltóságok és
fényszögek. A retrográd szorzó a konstruktor paramétere.
