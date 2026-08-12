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
        private readonly AppSettings $settings,
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
     *     draw_mode: string,
     *     chart_mode: string,
     *     datetime_utc: string
     * }
     */
    public function forNow(): array
    {
        $mode = $this->settings->drawMode();

        try {
            $chart = $this->chartContext->resolve();
            $result = $this->ephemeris->calculate([
                'datetime_utc' => $chart['datetime_utc'],
                'lat' => $chart['lat'],
                'lon' => $chart['lon'],
                'sidereal' => $chart['sidereal'],
            ]);

            $profile = $this->fromEphemerisResult($result, $mode);
            $profile['chart_mode'] = $chart['mode'];
            $profile['datetime_utc'] = $chart['datetime_utc'];

            return $profile;
        } catch (Throwable $e) {
            Log::warning('Card draw profile fallback used', ['error' => $e->getMessage()]);

            return $this->fallback($mode);
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
     *     slots: list<string>,
     *     draw_mode: string
     * }
     */
    public function fromEphemerisResult(array $result, ?string $mode = null): array
    {
        $mode = $this->normalizeMode($mode ?? $this->settings->drawMode());

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

        if ($mode === AppSettings::DRAW_MODE_RANDOM_POLARITY) {
            [$tones, $variants] = $this->randomWeightedSlotTones();
            $tone = $tones[0] ?? 'pozitív';
        } else {
            [$tones, $variants] = $this->slotTonesAndVariants($result, $tone);
        }

        $variant = $variants[0] ?? ($tone === 'negatív' ? 'shadow' : 'positive');

        return $this->withSlotAllocation([
            'sun_sign' => $sunSign,
            'element' => $element,
            'modality' => $modality,
            'tone' => $tone,
            'variant' => $variant,
            'tones' => $tones,
            'variants' => $variants,
            'draw_mode' => $mode,
        ]);
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
     *     slots: list<string>,
     *     draw_mode: string,
     *     chart_mode?: string,
     *     datetime_utc?: string
     * }
     */
    public function fallback(?string $mode = null): array
    {
        $mode = $this->normalizeMode($mode ?? $this->settings->drawMode());

        if ($mode === AppSettings::DRAW_MODE_RANDOM_POLARITY) {
            [$tones, $variants] = $this->randomWeightedSlotTones();
        } else {
            $tones = ['pozitív', 'pozitív', 'pozitív'];
            $variants = ['positive', 'positive', 'positive'];
        }

        return $this->withSlotAllocation([
            'sun_sign' => 'leo',
            'element' => 'tűz',
            'modality' => 'fő',
            'tone' => $tones[0] ?? 'pozitív',
            'variant' => $variants[0] ?? 'positive',
            'tones' => $tones,
            'variants' => $variants,
            'draw_mode' => $mode,
            'chart_mode' => 'now',
            'datetime_utc' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function withSlotAllocation(array $profile): array
    {
        $sunSign = (string) ($profile['sun_sign'] ?? 'leo');
        $element = (string) ($profile['element'] ?? 'tűz');
        $modality = (string) ($profile['modality'] ?? 'fő');

        if (! isset(self::ELEMENT_BACKGROUNDS[$element])) {
            $element = 'tűz';
            $profile['element'] = $element;
        }
        if (! isset(self::MODALITY_BACKGROUNDS[$modality])) {
            $modality = 'fő';
            $profile['modality'] = $modality;
        }

        $pool = [
            AppSettings::SLOT_SOURCE_SUN => [
                'id' => $sunSign,
                'value' => $sunSign,
            ],
            AppSettings::SLOT_SOURCE_ELEMENT => [
                'id' => self::ELEMENT_BACKGROUNDS[$element],
                'value' => $element,
            ],
            AppSettings::SLOT_SOURCE_MODALITY => [
                'id' => self::MODALITY_BACKGROUNDS[$modality],
                'value' => $modality,
            ],
        ];

        $sources = $this->settings->slotAllocation();
        $kindOptions = array_keys($pool);

        // random1 slots share a shuffled pool so each gets a distinct kind,
        // without being forced by fixed (napjegy/elem/minőség) admin choices.
        $random1Count = count(array_filter(
            $sources,
            static fn (string $source): bool => $source === AppSettings::SLOT_SOURCE_RANDOM1
        ));
        $random1Queue = $kindOptions;
        shuffle($random1Queue);
        if ($random1Count < count($random1Queue)) {
            $random1Queue = array_slice($random1Queue, 0, $random1Count);
        }

        $slots = [];
        $kinds = [];
        $values = [];

        foreach ($sources as $source) {
            if (isset($pool[$source])) {
                $kind = $source;
            } elseif ($source === AppSettings::SLOT_SOURCE_RANDOM1) {
                $kind = array_shift($random1Queue);
                if ($kind === null) {
                    $kind = $this->pick($kindOptions);
                }
            } else {
                // full_random: independent pick, repeats allowed
                $kind = $this->pick($kindOptions);
            }

            $slots[] = $pool[$kind]['id'];
            $kinds[] = $kind;
            $values[] = $pool[$kind]['value'];
        }

        $profile['slots'] = $slots;
        $profile['slot_sources'] = $sources;
        $profile['slot_kinds'] = $kinds;
        $profile['slot_values'] = $values;

        return $profile;
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

    /**
     * Random polarity with 60% positive / 40% negative per slot.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function randomWeightedSlotTones(): array
    {
        $tones = [];
        $variants = [];

        for ($slot = 0; $slot < 3; $slot++) {
            $besorolas = mt_rand(1, 100) <= 60 ? 'pozitív' : 'negatív';
            $tones[] = $besorolas;
            $variants[] = $besorolas === 'negatív' ? 'shadow' : 'positive';
        }

        return [$tones, $variants];
    }

    /**
     * @template T
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, [
            AppSettings::DRAW_MODE_SCORING,
            AppSettings::DRAW_MODE_RANDOM_POLARITY,
        ], true) ? $mode : AppSettings::DRAW_MODE_SCORING;
    }

    private function normalizeSign(string $sign): ?string
    {
        $key = strtolower(trim($sign));
        $key = preg_replace('/[^a-z0-9]+/', '', $key) ?? $key;

        return self::SIGN_ALIASES[$key] ?? null;
    }
}
