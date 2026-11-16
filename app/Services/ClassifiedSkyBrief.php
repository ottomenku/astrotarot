<?php

namespace App\Services;

use App\Models\BirthProfile;
use App\Models\Game;
use App\Models\User;
use Carbon\Carbon;
use Throwable;

class ClassifiedSkyBrief
{
    public function __construct(
        private readonly ReadingSkyLookup $sky,
        private readonly EphemerisChartContext $context,
        private readonly PlaceGeocoder $places,
        private readonly SkyBrief $brief,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function block(
        string $locale,
        ?Game $game,
        ?User $reader,
        array $payload,
        string $category = '',
        string $dimension = '',
    ): string {
        $modes = $game?->astroModes() ?? [];
        $parts = [];

        if (in_array(Game::ASTRO_DATA_NOW, $modes, true)) {
            $context = $this->context->resolve();
            $chart = $this->chartAt((string) $context['datetime_utc'], (float) $context['lat'], (float) $context['lon']);
            $when = Carbon::parse((string) $context['datetime_utc'])->timezone('Europe/Budapest');
            $parts[] = $this->chartBlock($locale, 'NOW', $chart, [
                'date' => $when->format('Y. m. d.'),
                'place' => trim((string) config('ephemeris.default_place', '')) ?: '—',
                'lat' => $context['lat'],
                'lon' => $context['lon'],
            ], null);
        }
        if (in_array(Game::ASTRO_DATA_PERSONAL, $modes, true)) {
            $choice = is_array($payload['sky_user'] ?? null) ? $payload['sky_user'] : null;
            $parts[] = $this->personBlock($locale, 'USER', $reader, $choice, true);
        }
        if (in_array(Game::ASTRO_DATA_RELATIONS, $modes, true)
            && ($category === Game::SPREAD_RELATION || $dimension === Game::DIMENSION_REL)) {
            $choice = is_array($payload['sky_relation'] ?? null) ? $payload['sky_relation'] : null;
            $parts[] = $this->personBlock($locale, 'RELATION', $reader, $choice, false);
        }
        if (in_array(Game::ASTRO_DATA_PROJECTION, $modes, true) && $category === Game::SPREAD_TIMING) {
            $parts[] = $this->projection($locale);
        }
        if ($parts === []) {
            return '';
        }

        $parts[] = $locale === 'en'
            ? 'R is retrograde, N is direct. A relation names only a later body, at the nominal angle. The celestial master and the celestial helper are inside NOW when that block is present. This message already holds the sky. Reply with the reading. Do not request more data.'
            : 'R retrográd, N normál. A reláció csak a listában később álló objektumot nevezi meg, a névleges szögön. Az égi mester és az égi segítő a NOW blokkban van, ha az szerepel. Az égi adat ebben az üzenetben van. Az olvasatot írd meg. Több adatot ne kérj.';

        return implode("\n\n", $parts);
    }

    /**
     * @param  array<string, mixed>|false|null  $chart
     * @param  array<string, mixed>  $meta
     */
    private function chartBlock(string $locale, string $title, array|false|null $chart, array $meta, ?string $introduction): string
    {
        if ($chart === false) {
            return $title."\nnone";
        }
        if (! is_array($chart)) {
            $missing = $locale === 'en' ? 'unavailable' : 'nem elérhető';

            return $title."\n".$missing;
        }

        $position = $locale === 'en' ? 'Positions' : 'Pozíciók';
        $relation = $locale === 'en' ? 'Relations' : 'Relációk';
        $lines = [$title, $this->header($locale, $meta, $introduction)];
        $lines[] = $position.': '.$this->brief->positions($chart, $locale);
        $lines[] = $relation.': '.$this->brief->relations($chart, $locale);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function header(string $locale, array $meta, ?string $introduction): string
    {
        $lat = isset($meta['lat']) ? $this->coord($meta['lat']) : '—';
        $lon = isset($meta['lon']) ? $this->coord($meta['lon']) : '—';
        $line = 'date: '.($meta['date'] ?? '—')
            .' hely: '.($meta['place'] ?? '—')
            .', long: '.$lon
            .' Lat: '.$lat
            .' House system: Placidus';
        if (! array_key_exists('name', $meta)) {
            return $line;
        }

        $intro = trim((string) $introduction);

        return $line."\n"
            .'ID(Name): '.($meta['name'] !== '' ? $meta['name'] : 'N/A')."\n"
            .($locale === 'en' ? 'Gender: ' : 'Nem: ').($meta['gender'] ?? 'N/A')."\n"
            .'Intro: '.($intro !== '' ? $intro : 'N/A');
    }

    private function coord(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>|null  $choice
     */
    private function personBlock(string $locale, string $title, ?User $reader, ?array $choice, bool $self): string
    {
        $chart = $this->personChart($reader, $choice, $self);

        return $this->chartBlock(
            $locale,
            $title,
            $chart,
            $this->personMeta($locale, $reader, $choice, $self),
            $this->introduction($reader, $choice, $self),
        );
    }

    /**
     * @param  array<string, mixed>|null  $choice
     * @return array<string, mixed>
     */
    private function personMeta(string $locale, ?User $reader, ?array $choice, bool $self): array
    {
        if ($choice === null) {
            return [];
        }
        if ($self && ($choice['source'] ?? '') === 'self' && $reader !== null) {
            $coords = $this->sky->chartCoordinates('birth', $reader);

            return [
                'date' => $reader->birth_at?->copy()->timezone('Europe/Budapest')->format('Y. m. d.') ?? '—',
                'place' => trim((string) $reader->birth_city) ?: '—',
                'lat' => $coords['lat'],
                'lon' => $coords['lon'],
                'name' => trim((string) $reader->name),
                'gender' => $this->genderLabel($locale, $reader->gender),
            ];
        }

        $profileId = (int) ($choice['profile_id'] ?? 0);
        if ($profileId > 0 && $reader !== null) {
            $profile = BirthProfile::query()->where('user_id', $reader->id)->find($profileId);
            if ($profile === null) {
                return [];
            }
            $coords = $this->coordinates(
                $profile->birth_latitude !== null ? (float) $profile->birth_latitude : null,
                $profile->birth_longitude !== null ? (float) $profile->birth_longitude : null,
                $profile->birth_country,
                $profile->birth_city,
            );

            return [
                'date' => $profile->birth_at?->copy()->timezone('Europe/Budapest')->format('Y. m. d.') ?? '—',
                'place' => trim((string) $profile->birth_city) ?: '—',
                'lat' => $coords['lat'],
                'lon' => $coords['lon'],
                'name' => trim((string) $profile->label),
                'gender' => 'N/A',
            ];
        }

        $birthAt = trim((string) ($choice['birth_at'] ?? ''));
        $coords = $this->coordinates(
            null,
            null,
            (string) ($choice['birth_country'] ?? ''),
            (string) ($choice['birth_city'] ?? ''),
        );

        return [
            'date' => $birthAt !== '' ? Carbon::parse($birthAt)->timezone('Europe/Budapest')->format('Y. m. d.') : '—',
            'place' => trim((string) ($choice['birth_city'] ?? '')) ?: '—',
            'lat' => $coords['lat'],
            'lon' => $coords['lon'],
            'name' => trim((string) ($choice['label'] ?? '')),
            'gender' => 'N/A',
        ];
    }

    private function genderLabel(string $locale, mixed $value): string
    {
        $value = strtolower(trim((string) $value));
        $labels = $locale === 'en'
            ? ['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'férfi' => 'Male', 'nő' => 'Female']
            : ['male' => 'Férfi', 'female' => 'Nő', 'other' => 'Egyéb', 'férfi' => 'Férfi', 'nő' => 'Nő'];

        return $labels[$value] ?? ($value !== '' ? (string) $value : 'N/A');
    }

    private function projection(string $locale): string
    {
        $context = $this->context->resolve();
        $lat = (float) $context['lat'];
        $lon = (float) $context['lon'];
        $start = Carbon::now('Europe/Budapest')->startOfDay();
        $blocks = ['PROJECTION'];
        $position = $locale === 'en' ? 'Positions' : 'Pozíciók';
        $relation = $locale === 'en' ? 'Relations' : 'Relációk';
        $missing = $locale === 'en' ? 'unavailable' : 'nem elérhető';

        for ($day = 0; $day < 7; $day++) {
            $noon = $start->copy()->addDays($day)->setTime(12, 0);
            $chart = $this->chartAt($noon->copy()->utc()->format('Y-m-d\TH:i:s\Z'), $lat, $lon);
            $date = $noon->format('Y. m. d.');
            if (! is_array($chart)) {
                $blocks[] = $date."\n".$missing;

                continue;
            }
            $lines = [
                $date,
                $position.': '.$this->brief->positions($chart, $locale),
                $relation.': '.$this->brief->relations($chart, $locale),
            ];
            foreach ([6 => 'Hold6', 12 => 'Hold12', 18 => 'Hold18', 24 => 'Hold24'] as $hour => $label) {
                if ($hour === 12) {
                    $slice = $this->brief->bodySlice($chart, 'Moon', $locale);
                } else {
                    $moment = $hour === 24
                        ? $start->copy()->addDays($day + 1)->startOfDay()
                        : $start->copy()->addDays($day)->setTime($hour, 0);
                    $moonChart = $this->chartAt($moment->utc()->format('Y-m-d\TH:i:s\Z'), $lat, $lon);
                    $slice = is_array($moonChart)
                        ? $this->brief->bodySlice($moonChart, 'Moon', $locale)
                        : ['position' => '—'];
                }
                $lines[] = $label.': '.$slice['position'];
            }
            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function chartAt(string $datetimeUtc, float $lat, float $lon): ?array
    {
        try {
            return $this->sky->chartFor($datetimeUtc, $lat, $lon);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * false means the person was declined. null means the chart could not be calculated.
     *
     * @param  array<string, mixed>|null  $choice
     * @return array<string, mixed>|false|null
     */
    private function personChart(?User $reader, ?array $choice, bool $self): array|false|null
    {
        if ($choice === null) {
            return false;
        }

        try {
            if ($self && ($choice['source'] ?? '') === 'self') {
                if ($reader?->birth_at === null) {
                    return false;
                }
                $coords = $this->sky->chartCoordinates('birth', $reader);

                return $this->sky->chartFor(
                    $reader->birth_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                    $coords['lat'],
                    $coords['lon'],
                );
            }

            $profileId = (int) ($choice['profile_id'] ?? 0);
            if ($profileId > 0 && $reader !== null) {
                $profile = BirthProfile::query()
                    ->where('user_id', $reader->id)
                    ->find($profileId);
                if ($profile === null) {
                    return false;
                }
                $coords = $this->coordinates(
                    $profile->birth_latitude,
                    $profile->birth_longitude,
                    $profile->birth_country,
                    $profile->birth_city,
                );

                return $this->sky->chartFor(
                    $profile->birth_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                    $coords['lat'],
                    $coords['lon'],
                );
            }

            $birthAt = trim((string) ($choice['birth_at'] ?? ''));
            if ($birthAt === '') {
                return false;
            }
            $coords = $this->coordinates(
                null,
                null,
                (string) ($choice['birth_country'] ?? ''),
                (string) ($choice['birth_city'] ?? ''),
            );

            return $this->sky->chartFor(
                Carbon::parse($birthAt)->utc()->format('Y-m-d\TH:i:s\Z'),
                $coords['lat'],
                $coords['lon'],
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $choice
     */
    private function introduction(?User $reader, ?array $choice, bool $self): ?string
    {
        if ($choice === null) {
            return null;
        }
        if ($self && ($choice['source'] ?? '') === 'self') {
            return $reader?->introduction;
        }
        $profileId = (int) ($choice['profile_id'] ?? 0);
        if ($profileId > 0 && $reader !== null) {
            return BirthProfile::query()->where('user_id', $reader->id)->find($profileId)?->introduction;
        }

        return isset($choice['introduction']) ? (string) $choice['introduction'] : null;
    }

    /**
     * @return array{lat: float, lon: float}
     */
    private function coordinates(?float $lat, ?float $lon, ?string $country, ?string $city): array
    {
        if ($lat !== null && $lon !== null) {
            return ['lat' => $lat, 'lon' => $lon];
        }

        $country = trim((string) $country);
        $city = trim((string) $city);
        if ($country !== '' && $city !== '') {
            try {
                $found = $this->places->coordinates($country, $city);
            } catch (Throwable) {
                $found = null;
            }
            if (is_array($found)) {
                return [
                    'lat' => (float) $found['latitude'],
                    'lon' => (float) $found['longitude'],
                ];
            }
        }

        return [
            'lat' => (float) config('ephemeris.default_lat'),
            'lon' => (float) config('ephemeris.default_lon'),
        ];
    }
}
