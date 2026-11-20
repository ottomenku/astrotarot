<?php

namespace App\Services;

use App\Models\Game;

class SpreadMatcher
{
    public function match(string $category, string $dimension): ?Game
    {
        $category = trim($category);
        $dimension = trim($dimension);
        if ($category === '') {
            return null;
        }
        if ($category === Game::SPREAD_NA && ($dimension === '' || $dimension === Game::DIMENSION_NA)) {
            return null;
        }

        $pool = [];
        foreach (Game::query()->active()->whereHas('rules')->get() as $game) {
            $types = $this->codes($game->spread_types);
            $dimensions = $this->codes($game->spread_dimensions);
            if ($types === [] || $dimensions === []) {
                continue;
            }
            if (! in_array($category, $types, true) || ! in_array($dimension, $dimensions, true)) {
                continue;
            }
            $pool[] = $game;
        }

        if ($pool === []) {
            return null;
        }

        $byOrder = $category === Game::SPREAD_NA || $dimension === Game::DIMENSION_NA;
        if (! $byOrder) {
            $fewest = min(array_map(
                fn (Game $game): int => count($this->codes($game->spread_dimensions)),
                $pool,
            ));
            $pool = array_values(array_filter(
                $pool,
                fn (Game $game): bool => count($this->codes($game->spread_dimensions)) === $fewest,
            ));
        }

        $bestOrder = null;
        $winners = [];
        foreach ($pool as $game) {
            $order = $this->categoryOrder($game);
            if ($bestOrder === null || $order < $bestOrder) {
                $bestOrder = $order;
                $winners = [$game];
            } elseif ($order === $bestOrder) {
                $winners[] = $game;
            }
        }

        return $winners[random_int(0, count($winners) - 1)];
    }

    /**
     * @return list<string>
     */
    private function codes(mixed $stored): array
    {
        return array_values(array_filter(
            (array) $stored,
            fn (mixed $code): bool => is_string($code) && $code !== '',
        ));
    }

    private function categoryOrder(Game $game): int
    {
        $order = (int) $game->category_order;

        return max(1, min(10, $order === 0 ? 1 : $order));
    }
}
