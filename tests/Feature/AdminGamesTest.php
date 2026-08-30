<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameRule;
use App\Models\User;
use App\Support\CardPackCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_edit_a_game(): void
    {
        $admin = User::factory()->admin()->create();
        $packs = CardPackCatalog::slugs();
        $this->assertContains('egi-orakulum', $packs);
        $this->assertContains('tarot', $packs);

        $this->actingAs($admin)
            ->get(route('admin.games.index'))
            ->assertOk()
            ->assertSee('Asztro tarot', false);

        $this->actingAs($admin)
            ->get(route('admin.games.create'))
            ->assertOk()
            ->assertSee(__('admin.games.prompt_hu'), false)
            ->assertSee(__('admin.games.add_rule'), false);

        $this->actingAs($admin)
            ->post(route('admin.games.store'), [
                'name_hu' => 'Hétnapos',
                'name_en' => 'Seven day',
                'slug' => 'hetnapos',
                'description_hu' => 'Hét lapos húzás.',
                'description_en' => 'A seven-card draw.',
                'has_question' => '1',
                'prompt_hu' => 'Magyar prompt',
                'prompt_en' => 'English prompt',
                'is_active' => '1',
                'sort_order' => 4,
                'rules' => [
                    [
                        'pack_slug' => 'cigany-kartya',
                        'title_hu' => 'Múlt',
                        'title_en' => 'Past',
                        'info_mode' => GameRule::INFO_MEANING,
                        'row' => 1,
                        'place' => 1,
                    ],
                    [
                        'pack_slug' => 'cigany-kartya',
                        'title_hu' => 'Jelen',
                        'title_en' => 'Present',
                        'info_mode' => GameRule::INFO_MEANING,
                        'row' => 1,
                        'place' => 2,
                    ],
                ],
            ])
            ->assertRedirect();

        $game = Game::query()->where('slug', 'hetnapos')->firstOrFail();
        $this->assertSame('Hétnapos', $game->name_hu);
        $this->assertTrue($game->has_question);
        $this->assertFalse($game->is_default);
        $this->assertSame(2, $game->rules()->count());
        $this->assertSame('cigany-kartya', $game->rules()->first()->pack_slug);

        $this->actingAs($admin)
            ->put(route('admin.games.update', $game), [
                'name_hu' => 'Hétnapos plusz',
                'name_en' => 'Seven day plus',
                'slug' => 'hetnapos',
                'description_hu' => 'Frissítve',
                'description_en' => 'Updated',
                'has_question' => '0',
                'is_default' => '1',
                'is_active' => '1',
                'sort_order' => 4,
                'prompt_hu' => 'Magyar prompt',
                'prompt_en' => 'English prompt',
                'rules' => [
                    [
                        'pack_slug' => 'tarot',
                        'title_hu' => 'Figura',
                        'title_en' => 'Figure',
                        'info_mode' => GameRule::INFO_MEANING,
                        'row' => 1,
                        'place' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.games.edit', $game));

        $game->refresh();
        $this->assertSame('Hétnapos plusz', $game->name_hu);
        $this->assertFalse($game->has_question);
        $this->assertTrue($game->is_default);
        $this->assertSame(1, $game->rules()->count());
        $this->assertFalse(Game::query()->where('slug', 'astro-tarot')->value('is_default'));
    }

    public function test_non_admin_cannot_manage_games(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.games.index'))
            ->assertForbidden();
    }

    public function test_duplicate_place_in_same_row_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.games.store'), [
                'name_hu' => 'Hibás',
                'name_en' => 'Broken',
                'sort_order' => 8,
                'is_active' => '1',
                'rules' => [
                    [
                        'pack_slug' => 'tarot',
                        'title_hu' => 'A',
                        'title_en' => 'A',
                        'info_mode' => GameRule::INFO_NONE,
                        'row' => 1,
                        'place' => 1,
                    ],
                    [
                        'pack_slug' => 'tarot',
                        'title_hu' => 'B',
                        'title_en' => 'B',
                        'info_mode' => GameRule::INFO_NONE,
                        'row' => 1,
                        'place' => 1,
                    ],
                ],
            ])
            ->assertSessionHasErrors();
    }
}
