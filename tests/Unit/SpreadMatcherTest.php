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

    protected function setUp(): void
    {
        parent::setUp();

        Game::query()->update(['is_active' => false]);
    }

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

    public function test_fewer_dimensions_win_before_category_order(): void
    {
        $specific = $this->game(['BINARY', 'CHOICE'], ['REL'], 8);
        $this->game(['BINARY'], ['REL', 'FIN'], 1);

        $match = app(SpreadMatcher::class)->match('BINARY', 'REL');

        $this->assertSame($specific->id, $match?->id);
    }

    public function test_category_order_picks_the_lower_number_when_dimension_counts_match(): void
    {
        $this->game(['CHOICE'], ['CAR', 'FIN'], 6);
        $preferred = $this->game(['CHOICE', 'BINARY'], ['CAR', 'FIN'], 2);

        $match = app(SpreadMatcher::class)->match('CHOICE', 'FIN');

        $this->assertSame($preferred->id, $match?->id);
    }

    public function test_an_na_dimension_uses_the_lowest_category_order(): void
    {
        $preferred = $this->game(['FORECAST'], ['N/A', 'CAR'], 1);
        $this->game(['FORECAST'], ['N/A'], 3);

        $match = app(SpreadMatcher::class)->match('FORECAST', 'N/A');

        $this->assertSame($preferred->id, $match?->id);
    }

    public function test_an_unclassified_category_with_a_dimension_follows_category_order(): void
    {
        $preferred = $this->game(['N/A'], ['REL', 'SOC'], 1);
        $this->game(['N/A'], ['REL'], 4);

        $match = app(SpreadMatcher::class)->match('N/A', 'REL');

        $this->assertSame($preferred->id, $match?->id);
    }

    public function test_an_unclassified_question_without_a_dimension_does_not_pick_a_spread(): void
    {
        $this->game(['N/A'], ['N/A'], 1);

        $this->assertNull(app(SpreadMatcher::class)->match('N/A', 'N/A'));
        $this->assertNull(app(SpreadMatcher::class)->match('N/A', ''));
    }

    /**
     * @param  list<string>  $types
     * @param  list<string>  $dimensions
     */
    private function game(array $types, array $dimensions, int $categoryOrder = 1): Game
    {
        $game = Game::factory()->create([
            'spread_types' => $types,
            'spread_dimensions' => $dimensions,
            'category_order' => $categoryOrder,
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
