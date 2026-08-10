<?php

namespace App\Services;

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

    /** Elem → háttér asset id. */
    private const ELEMENT_BACKGROUNDS = [
        'tűz' => 'fire',
        'víz' => 'water',
        'föld' => 'earth',
        'levegő' => 'air',
    ];

    /** Minőség → háttér asset id. */
    private const MODALITY_BACKGROUNDS = [
        'fő' => 'cardinal',
        'szilárd' => 'fixed',
        'változó' => 'mutable',
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
     *     slots: list<string>,
     *     chart_mode: string,
     *     datetime_utc: string
     * }
     */
    public function forNow(): array
    {
        try {
            $chart = $this->chartContext->resolve();
            $result = $this->ephemeris->calculate([
                'datetime_utc' => $chart['datetime_utc'],
                'lat' => $chart['lat'],
                'lon' => $chart['lon'],
                'sidereal' => $chart['sidereal'],
            ]);

            $profile = $this->fromEphemerisResult($result);
            $profile['chart_mode'] = $chart['mode'];
            $profile['datetime_utc'] = $chart['datetime_utc'];

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
     *     slots: list<string>
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

        if (! isset(self::ELEMENT_BACKGROUNDS[$element])) {
            $element = 'tűz';
        }
        if (! isset(self::MODALITY_BACKGROUNDS[$modality])) {
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
            'slots' => [
                $sunSign,
                self::ELEMENT_BACKGROUNDS[$element],
                self::MODALITY_BACKGROUNDS[$modality],
            ],
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
     *     slots: list<string>
     * }
     */
    public function fallback(): array
    {
        return [
            'sun_sign' => 'leo',
            'element' => 'tűz',
            'modality' => 'fő',
            'tone' => 'pozitív',
            'variant' => 'positive',
            'tones' => ['pozitív', 'pozitív', 'pozitív'],
            'variants' => ['positive', 'positive', 'positive'],
            'slots' => ['leo', 'fire', 'cardinal'],
            'chart_mode' => 'now',
            'datetime_utc' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ];
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
