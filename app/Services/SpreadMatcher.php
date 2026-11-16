<?php

namespace App\Services;

use App\Models\Game;

class SpreadMatcher
{
    public function match(string $category, string $dimension): ?Game
    {
        $games = Game::query()
            ->active()
            ->whereHas('rules')
            ->get();

        $bestScore = null;
        $pool = [];
        foreach ($games as $game) {
            $types = array_values(array_filter(
                (array) $game->spread_types,
                fn (mixed $code): bool => is_string($code) && $code !== '',
            ));
            $dimensions = array_values(array_filter(
                (array) $game->spread_dimensions,
                fn (mixed $code): bool => is_string($code) && $code !== '',
            ));
            if ($types === [] || $dimensions === []) {
                continue;
            }
            if (! in_array($category, $types, true) || ! in_array($dimension, $dimensions, true)) {
                continue;
            }
            $score = count($types) + count($dimensions);
            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $pool = [$game];
            } elseif ($score === $bestScore) {
                $pool[] = $game;
            }
        }

        if ($pool === []) {
            return null;
        }

        return $pool[random_int(0, count($pool) - 1)];
    }
}
