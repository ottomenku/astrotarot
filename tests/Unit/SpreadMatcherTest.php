<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameRule;
use App\Services\SpreadMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpreadMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_more_specific_spread_wins_and_empty_lists_never_match(): void
    {
        $this->game(['BINARY', 'CHOICE'], ['REL', 'FIN']);
        $specific = $this->game(['BINARY'], ['REL']);
        $this->game(['BINARY'], []);
        $this->game([], ['REL']);
        $this->game(['BINARY'], ['REL'])->update(['is_active' => false]);
        $withoutRules = Game::factory()->create([
            'spread_types' => ['BINARY'],
            'spread_dimensions' => ['REL'],
        ]);
        $this->assertNull($withoutRules->rules()->first());

        $match = app(SpreadMatcher::class)->match('BINARY', 'REL');

        $this->assertNotNull($match);
        $this->assertSame($specific->id, $match->id);
    }

    public function test_dimension_na_matches_only_a_spread_that_ticks_it(): void
    {
        $open = $this->game(['EXPLORATORY'], ['N/A']);
        $this->game(['EXPLORATORY'], ['REL']);

        $match = app(SpreadMatcher::class)->match('EXPLORATORY', 'N/A');

        $this->assertSame($open->id, $match?->id);
    }

    public function test_a_tie_returns_one_of_the_equally_specific_spreads(): void
    {
        $first = $this->game(['TIMING'], ['CAR']);
        $second = $this->game(['TIMING'], ['CAR']);

        $match = app(SpreadMatcher::class)->match('TIMING', 'CAR');

        $this->assertContains($match?->id, [$first->id, $second->id]);
    }

    /**
     * @param  list<string>  $types
     * @param  list<string>  $dimensions
     */
    private function game(array $types, array $dimensions): Game
    {
        $game = Game::factory()->create([
            'spread_types' => $types,
            'spread_dimensions' => $dimensions,
        ]);
        GameRule::query()->create([
            'game_id' => $game->id,
            'pack_slug' => 'tarot',
            'title_hu' => 'Lap',
            'title_en' => 'Card',
            'info_mode' => 'meaning',
            'row' => 1,
            'place' => 1,
            'draw_order' => 1,
        ]);

        return $game;
    }
}
