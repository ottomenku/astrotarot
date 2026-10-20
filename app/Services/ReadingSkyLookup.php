<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Throwable;

class ReadingSkyLookup
{
    public function __construct(private readonly EphemerisService $ephemeris) {}

    /**
     * @param  list<array<string, mixed>>  $requests
     * @return list<array<string, mixed>>
     */
    public function fulfill(array $requests, ?User $user): array
    {
        $results = [];
        foreach (array_slice($requests, 0, 4) as $request) {
            $results[] = $this->fulfillOne($request, $user);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function fulfillOne(array $request, ?User $user): array
    {
        $kind = (string) ($request['kind'] ?? '');
        $base = [
            'id' => (string) ($request['id'] ?? ''),
            'kind' => $kind,
        ];

        try {
            if ($kind === 'projection') {
                return array_merge($base, $this->projection($request));
            }

            $moment = $this->moment((string) ($request['moment'] ?? 'now'), $user);
            if (isset($moment['error'])) {
                return array_merge($base, $moment);
            }

            $chart = $this->ephemeris->calculate([
                'datetime_utc' => $moment['datetime_utc'],
                'lat' => (float) config('ephemeris.default_lat'),
                'lon' => (float) config('ephemeris.default_lon'),
            ]);

            $include = $kind === 'houses'
                ? ['houses']
                : (array) ($request['include'] ?? []);
            if ($include === []) {
                $include = ['planets', 'signs', 'aspects'];
            }

            return array_merge($base, [
                'moment' => $moment['label'],
                'datetime_utc' => $moment['datetime_utc'],
                'location' => 'default_site_coordinates',
                'data' => $this->present($chart, $include),
            ]);
        } catch (Throwable $e) {
            return array_merge($base, [
                'error' => 'unavailable',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $chart
     * @param  list<string>  $include
     * @return array<string, mixed>
     */
    public function present(array $chart, array $include): array
    {
        $bodies = $this->bodies($chart);
        $stars = $this->stars($chart);
        $aspects = $this->aspects($bodies);
        $data = [];

        if (in_array('planets', $include, true) || in_array('signs', $include, true)) {
            $rows = array_map(fn (array $body): array => $this->bodyRow($body), $bodies);
            if (in_array('planets', $include, true)) {
                $data['planets'] = $rows;
            }
            if (in_array('signs', $include, true)) {
                $data['signs'] = $rows;
            }
        }

        if (in_array('aspects', $include, true)) {
            $data['aspects'] = $aspects;
        }

        if (in_array('fixed_stars', $include, true)) {
            $data['fixed_stars'] = array_map(fn (array $star): array => [
                'name' => $star['name'],
                'sign' => $star['sign'],
                'degree' => $star['degree'],
                'house' => $star['house'],
            ], $stars);
        }

        if (in_array('houses', $include, true)) {
            $data['houses'] = $this->houses($chart, $bodies, $stars, $aspects);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function projection(array $request): array
    {
        $from = trim((string) ($request['from'] ?? ''));
        $until = trim((string) ($request['until'] ?? ''));
        if ($from === '') {
            $from = now()->utc()->format('Y-m-d\TH:i:s\Z');
        }
        if ($until === '') {
            $until = Carbon::parse($from)->utc()->addDays(30)->format('Y-m-d\TH:i:s\Z');
        }

        $result = $this->ephemeris->project([
            'body' => (string) ($request['body'] ?? ''),
            'event' => (string) ($request['event'] ?? ''),
            'aspect' => (string) ($request['aspect'] ?? ''),
            'with' => (string) ($request['with'] ?? ''),
            'house' => $request['house'] ?? null,
            'from' => $from,
            'until' => $until,
            'lat' => (float) config('ephemeris.default_lat'),
            'lon' => (float) config('ephemeris.default_lon'),
        ]);

        return [
            'moment' => 'projection',
            'location' => 'default_site_coordinates',
            'data' => $result,
        ];
    }

    /**
     * @return array{datetime_utc?: string, label?: string, error?: string}
     */
    private function moment(string $moment, ?User $user): array
    {
        $moment = trim($moment);
        if ($moment === '' || $moment === 'now') {
            $now = now()->utc();

            return [
                'datetime_utc' => $now->format('Y-m-d\TH:i:s\Z'),
                'label' => 'now',
            ];
        }

        if ($moment === 'birth') {
            if ($user?->birth_at === null) {
                return ['error' => 'birth_unknown'];
            }

            return [
                'datetime_utc' => $user->birth_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'label' => 'birth',
            ];
        }

        try {
            $parsed = Carbon::parse($moment)->utc();
        } catch (Throwable) {
            return ['error' => 'invalid_moment'];
        }

        return [
            'datetime_utc' => $parsed->format('Y-m-d\TH:i:s\Z'),
            'label' => $parsed->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return list<array<string, mixed>>
     */
    private function bodies(array $chart): array
    {
        $rows = [];
        foreach ((array) ($chart['objects'] ?? []) as $object) {
            if (! is_array($object) || ($object['kind'] ?? '') === 'fixed_star') {
                continue;
            }
            if (! isset($object['name'], $object['longitude'])) {
                continue;
            }
            $rows[] = $object;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return list<array<string, mixed>>
     */
    private function stars(array $chart): array
    {
        $rows = [];
        foreach ((array) ($chart['objects'] ?? []) as $object) {
            if (! is_array($object) || ($object['kind'] ?? '') !== 'fixed_star') {
                continue;
            }
            $rows[] = $object;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function bodyRow(array $body): array
    {
        return [
            'name' => (string) $body['name'],
            'sign' => (string) ($body['sign'] ?? ''),
            'degree' => $body['degree'] ?? null,
            'house' => $body['house'] ?? null,
            'retrograde' => (bool) ($body['retrograde'] ?? false),
            'fixed_star' => $body['fixed_star'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $bodies
     * @return list<array<string, mixed>>
     */
    private function aspects(array $bodies): array
    {
        $catalog = [
            ['name' => 'conjunction', 'angle' => 0.0, 'orb' => 8.0],
            ['name' => 'sextile', 'angle' => 60.0, 'orb' => 4.0],
            ['name' => 'square', 'angle' => 90.0, 'orb' => 6.0],
            ['name' => 'trine', 'angle' => 120.0, 'orb' => 6.0],
            ['name' => 'quincunx', 'angle' => 150.0, 'orb' => 3.0],
            ['name' => 'opposition', 'angle' => 180.0, 'orb' => 8.0],
        ];

        $found = [];
        $count = count($bodies);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $distance = $this->angleDelta((float) $bodies[$i]['longitude'], (float) $bodies[$j]['longitude']);
                $best = null;
                foreach ($catalog as $aspect) {
                    $orb = abs($distance - $aspect['angle']);
                    if ($orb > $aspect['orb']) {
                        continue;
                    }
                    if ($best === null || $orb < $best['orb']) {
                        $best = [
                            'a' => (string) $bodies[$i]['name'],
                            'b' => (string) $bodies[$j]['name'],
                            'aspect' => $aspect['name'],
                            'orb' => round($orb, 2),
                        ];
                    }
                }
                if ($best !== null) {
                    $found[] = $best;
                }
            }
        }

        usort($found, fn (array $left, array $right): int => $left['orb'] <=> $right['orb']);

        return array_slice($found, 0, 30);
    }

    /**
     * @param  array<string, mixed>  $chart
     * @param  list<array<string, mixed>>  $bodies
     * @param  list<array<string, mixed>>  $stars
     * @param  list<array<string, mixed>>  $aspects
     * @return list<array<string, mixed>>
     */
    private function houses(array $chart, array $bodies, array $stars, array $aspects): array
    {
        $cusps = [];
        foreach ((array) ($chart['houses'] ?? []) as $house) {
            if (! is_array($house) || ! isset($house['house'])) {
                continue;
            }
            $cusps[(int) $house['house']] = trim(((string) ($house['sign'] ?? '')).' '.($house['degree'] ?? ''));
        }

        $rows = [];
        for ($number = 1; $number <= 12; $number++) {
            $occupants = [];
            foreach (array_merge($bodies, $stars) as $object) {
                if ((int) ($object['house'] ?? 0) !== $number) {
                    continue;
                }
                $occupants[] = (string) $object['name'];
            }
            $received = [];
            foreach ($aspects as $aspect) {
                if (in_array($aspect['a'], $occupants, true) || in_array($aspect['b'], $occupants, true)) {
                    $received[] = $aspect;
                }
            }
            if ($occupants === [] && $received === [] && ! isset($cusps[$number])) {
                continue;
            }
            $rows[] = [
                'house' => $number,
                'cusp' => $cusps[$number] ?? null,
                'occupants' => $occupants,
                'aspects' => $received,
            ];
        }

        return $rows;
    }

    private function angleDelta(float $a, float $b): float
    {
        $distance = abs(fmod($a, 360.0) - fmod($b, 360.0));

        return min($distance, 360.0 - $distance);
    }
}
