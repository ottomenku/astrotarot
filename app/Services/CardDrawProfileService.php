<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CardDrawProfileService
{
    private const SIGN_ALIASES = [
        'aries' => 'aries', 'kos' => 'aries',
        'taurus' => 'taurus', 'bika' => 'taurus',
        'gemini' => 'gemini', 'ikrek' => 'gemini',
        'cancer' => 'cancer', 'rak' => 'cancer',
        'leo' => 'leo', 'oroszlan' => 'leo',
        'virgo' => 'virgo', 'szuz' => 'virgo',
        'libra' => 'libra', 'merleg' => 'libra',
        'scorpio' => 'scorpio', 'skorpio' => 'scorpio',
        'sagittarius' => 'sagittarius', 'nyilas' => 'sagittarius',
        'capricorn' => 'capricorn', 'bak' => 'capricorn',
        'aquarius' => 'aquarius', 'vizonto' => 'aquarius',
        'pisces' => 'pisces', 'halak' => 'pisces',
    ];

    /** @var list<string> */
    private const ELEMENTS = ['tűz', 'víz', 'föld', 'levegő'];

    /** @var list<string> */
    private const MODALITIES = ['fő', 'szilárd', 'változó'];

    /** Objektumnév → chart id (angol és magyar címkék). */
    private const CHART_OBJECT_IDS = [
        'Sun' => 'sun',
        'Moon' => 'moon',
        'Mercury' => 'mercury',
        'Venus' => 'venus',
        'Mars' => 'mars',
        'Jupiter' => 'jupiter',
        'Saturn' => 'saturn',
        'Uranus' => 'uranus',
        'Neptune' => 'neptune',
        'Pluto' => 'pluto',
        'True Node' => 'true_node',
        'Mean Lilith' => 'lilith',
        'ASC' => 'asc',
        'MC' => 'mc',
        'South Node' => 'south_node',
        'Nap' => 'sun',
        'Hold' => 'moon',
        'Merkúr' => 'mercury',
        'Vénusz' => 'venus',
        'Szaturnusz' => 'saturn',
        'Uránusz' => 'uranus',
        'Neptunusz' => 'neptune',
        'Plútó' => 'pluto',
        'Felszálló Holdcsomó' => 'true_node',
        'Leszálló Holdcsomó' => 'south_node',
        'Lilith' => 'lilith',
    ];

    public function __construct(
        private readonly EphemerisService $ephemeris,
        private readonly EphemerisChartContext $chartContext,
    ) {}

    /**
     * @return array{
     *     sun_sign: string,
     *     element: string,
     *     modality: string,
     *     tone: string,
     *     variant: string,
     *     variants: list<string>,
     *     tones: list<string>,
     *     draw_mode: string,
     *     chart_mode: string,
     *     datetime_utc: string,
     *     chart?: array{objects: list<array<string, mixed>>, aspects: list<array<string, mixed>>}
     * }
     */
    public function forNow(): array
    {
        $chart = $this->chartContext->resolve();
        $minute = substr((string) $chart['datetime_utc'], 0, 16);
        $key = 'card-draw-profile:'.md5(json_encode([
            $minute,
            $chart['mode'],
            $chart['lat'],
            $chart['lon'],
            $chart['sidereal'],
        ]));

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $result = $this->ephemeris->calculate([
                'datetime_utc' => $chart['datetime_utc'],
                'lat' => $chart['lat'],
                'lon' => $chart['lon'],
                'sidereal' => $chart['sidereal'],
            ]);

            $profile = $this->fromEphemerisResult($result);
            $profile['chart_mode'] = $chart['mode'];
            $profile['datetime_utc'] = $chart['datetime_utc'];
            $profile['chart'] = $this->chartSnapshot($result);
            Cache::put($key, $profile, now()->addMinutes(2));

            return $profile;
        } catch (Throwable $e) {
            Log::warning('Card draw profile fallback used', ['error' => $e->getMessage()]);

            return $this->fallback();
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{
     *     sun_sign: string,
     *     element: string,
     *     modality: string,
     *     tone: string,
     *     variant: string,
     *     variants: list<string>,
     *     tones: list<string>,
     *     draw_mode: string
     * }
     */
    public function fromEphemerisResult(array $result): array
    {
        $sun = collect($result['objects'] ?? [])->firstWhere('name', 'Sun');
        $sunSign = $this->normalizeSign(is_array($sun) ? (string) ($sun['sign'] ?? '') : '') ?? 'leo';

        $clean = $result['scoring']['summary_clean'] ?? [];
        $element = (string) ($clean['elem_dominancia']['nev'] ?? 'tűz');
        $modality = (string) ($clean['minoseg_dominancia']['nev'] ?? 'fő');
        $tone = (string) ($clean['pozitiv_negativ']['nev'] ?? 'pozitív');

        if (! in_array($element, self::ELEMENTS, true)) {
            $element = 'tűz';
        }
        if (! in_array($modality, self::MODALITIES, true)) {
            $modality = 'fő';
        }
        if (! in_array($tone, ['pozitív', 'negatív'], true)) {
            $tone = 'pozitív';
        }

        [$tones, $variants] = $this->slotTonesAndVariants($result, $tone);

        $variant = $variants[0] ?? ($tone === 'negatív' ? 'shadow' : 'positive');

        return [
            'sun_sign' => $sunSign,
            'element' => $element,
            'modality' => $modality,
            'tone' => $tone,
            'variant' => $variant,
            'tones' => $tones,
            'variants' => $variants,
            'draw_mode' => 'scoring',
            'scoring' => $this->scoringSnapshot($clean, $element, $modality, $tone),
        ];
    }

    /**
     * @return array{
     *     sun_sign: string,
     *     element: string,
     *     modality: string,
     *     tone: string,
     *     variant: string,
     *     variants: list<string>,
     *     tones: list<string>,
     *     draw_mode: string,
     *     chart_mode?: string,
     *     datetime_utc?: string
     * }
     */
    public function fallback(): array
    {
        $tones = ['pozitív', 'pozitív', 'pozitív'];
        $variants = ['positive', 'positive', 'positive'];
        $tone = 'pozitív';

        return [
            'sun_sign' => 'leo',
            'element' => 'tűz',
            'modality' => 'fő',
            'tone' => $tone,
            'variant' => 'positive',
            'tones' => $tones,
            'variants' => $variants,
            'draw_mode' => 'scoring',
            'chart_mode' => 'now',
            'datetime_utc' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            'chart' => [
                'objects' => [],
                'aspects' => [],
            ],
            'scoring' => $this->scoringSnapshot([], 'tűz', 'fő', $tone),
        ];
    }

    /**
     * @param  array<string, mixed>  $clean
     * @return array<string, mixed>
     */
    private function scoringSnapshot(array $clean, string $element, string $modality, string $tone): array
    {
        $elemDom = is_array($clean['elem_dominancia'] ?? null) ? $clean['elem_dominancia'] : [];
        $modDom = is_array($clean['minoseg_dominancia'] ?? null) ? $clean['minoseg_dominancia'] : [];
        $toneDom = is_array($clean['pozitiv_negativ'] ?? null) ? $clean['pozitiv_negativ'] : [];

        return [
            'elements' => is_array($clean['elemek'] ?? null) ? $clean['elemek'] : [],
            'element_name' => $element,
            'element_value' => $elemDom['nyers_ertek'] ?? $elemDom['ertek'] ?? null,
            'modalities' => is_array($clean['minosegek'] ?? null) ? $clean['minosegek'] : [],
            'modality_name' => $modality,
            'modality_value' => $modDom['nyers_ertek'] ?? $modDom['ertek'] ?? null,
            'tone_name' => $tone,
            'tone_positive' => $toneDom['nyers_pozitiv'] ?? $toneDom['pozitiv'] ?? null,
            'tone_negative' => $toneDom['nyers_negativ'] ?? $toneDom['negativ'] ?? null,
            'tone_net' => $toneDom['netto_ertek'] ?? null,
        ];
    }

    /**
     * Compact chart payload for the homepage captions.
     *
     * @param  array<string, mixed>  $result
     * @return array{objects: list<array<string, mixed>>, aspects: list<array<string, mixed>>}
     */
    private function chartSnapshot(array $result): array
    {
        $objects = [];
        foreach ($result['objects'] ?? [] as $object) {
            if (! is_array($object)) {
                continue;
            }
            $kind = (string) ($object['kind'] ?? '');
            if (! in_array($kind, ['planet', 'point'], true)) {
                continue;
            }
            $name = (string) ($object['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $objects[] = [
                'id' => $this->chartObjectId($name),
                'name' => $name,
                'kind' => $kind,
                'sign' => $this->normalizeSign((string) ($object['sign'] ?? '')) ?? strtolower((string) ($object['sign'] ?? '')),
                'sign_label' => (string) ($object['sign'] ?? ''),
                'degree' => round((float) ($object['degree'] ?? 0), 1),
                'house' => isset($object['house']) ? (int) $object['house'] : null,
                'retrograde' => (bool) ($object['retrograde'] ?? false),
            ];
        }

        $nameToId = self::CHART_OBJECT_IDS;
        foreach ($objects as $object) {
            $nameToId[$object['name']] = $object['id'];
        }

        $aspects = [];
        foreach ($result['scoring']['aspects'] ?? [] as $aspect) {
            if (! is_array($aspect)) {
                continue;
            }
            $a = (string) ($aspect['objektum_1'] ?? '');
            $b = (string) ($aspect['objektum_2'] ?? '');
            $aspects[] = [
                'a' => $a,
                'b' => $b,
                'a_id' => $nameToId[$a] ?? $this->chartObjectId($a),
                'b_id' => $nameToId[$b] ?? $this->chartObjectId($b),
                'type' => (string) ($aspect['fenyszog'] ?? ''),
                'orb' => (float) ($aspect['orb'] ?? 0),
                'tone' => (string) ($aspect['jelleg'] ?? ''),
            ];
        }

        return [
            'objects' => $objects,
            'aspects' => $aspects,
        ];
    }

    private function chartObjectId(string $name): string
    {
        return self::CHART_OBJECT_IDS[$name] ?? strtolower(str_replace(' ', '_', $name));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{0: list<string>, 1: list<string>}
     */
    private function slotTonesAndVariants(array $result, string $fallbackTone): array
    {
        $positions = $result['scoring']['tarot_helyek']['positions'] ?? null;
        $tones = [];
        $variants = [];

        for ($slot = 0; $slot < 3; $slot++) {
            $besorolas = is_array($positions[$slot] ?? null)
                ? (string) ($positions[$slot]['besorolas'] ?? $fallbackTone)
                : $fallbackTone;

            if (! in_array($besorolas, ['pozitív', 'negatív'], true)) {
                $besorolas = $fallbackTone;
            }

            $tones[] = $besorolas;
            $variants[] = $besorolas === 'negatív' ? 'shadow' : 'positive';
        }

        return [$tones, $variants];
    }

    private function normalizeSign(string $sign): ?string
    {
        $key = strtolower(trim($sign));
        $key = preg_replace('/[^a-z0-9]+/', '', $key) ?? $key;

        return self::SIGN_ALIASES[$key] ?? null;
    }
}
