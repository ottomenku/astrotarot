<?php

namespace Tests\Feature;

use App\Models\CardPack;
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
            ->assertSee(__('admin.games.add_rule'), false)
            ->assertSee(__('admin.games.slot_key'), false)
            ->assertSee(__('admin.games.slot_description_hu'), false)
            ->assertSee(__('admin.games.slot_description_en'), false)
            ->assertSee(__('admin.games.card_back'), false)
            ->assertSee('data-remove-rule', false)
            ->assertSee('window.confirm(removeConfirm)', false)
            ->assertDontSee('Laphely infó', false)
            ->assertDontSee('Ha a felhasználó nem választ paklit', false);

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
        $this->assertSame('tarot', $game->rules()->first()->pack_type);
        $this->assertSame(
            CardPack::query()->where('slug', 'cigany-kartya')->value('id'),
            $game->rules()->first()->default_pack_id
        );

        $this->actingAs($admin)
            ->withSession(['spread' => ['game' => 1, 'back' => 4]])
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
        $this->assertNull(session('spread.game'));
        $this->assertSame(4, session('spread.back'));
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

    public function test_game_rule_can_use_category_default_pack(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.games.store'), [
                'name_hu' => 'Alapért',
                'name_en' => 'Defaulted',
                'sort_order' => 9,
                'is_active' => '1',
                'rules' => [
                    [
                        'pack_type' => 'oracle',
                        'title_hu' => 'Mester',
                        'title_en' => 'Master',
                        'info_mode' => GameRule::INFO_ASTRO,
                        'row' => 1,
                        'place' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $rule = Game::query()->where('slug', 'alapert')->firstOrFail()->rules()->first();
        $this->assertSame('oracle', $rule->pack_type);
        $this->assertNull($rule->default_pack_id);
        $this->assertSame('egi-orakulum', $rule->pack_slug);
    }

    public function test_rule_stores_slot_key_description_and_card_back(): void
    {
        $admin = User::factory()->admin()->create();
        $back = CardPack::query()->where('type', CardPack::TYPE_CARD_BACK)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.games.store'), [
                'name_hu' => 'Azonosítós',
                'name_en' => 'Keyed',
                'sort_order' => 3,
                'is_active' => '1',
                'rules' => [
                    [
                        'pack_type' => 'tarot',
                        'title_hu' => 'Múlt',
                        'title_en' => 'Past',
                        'slot_key' => 'mult',
                        'description_hu' => 'A múlt lapja.',
                        'description_en' => 'The past card.',
                        'card_back_id' => $back->id,
                        'row' => 1,
                        'place' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $rule = Game::query()->where('slug', 'azonositos')->firstOrFail()->rules()->first();
        $this->assertSame('mult', $rule->slot_key);
        $this->assertSame('A múlt lapja.', $rule->description_hu);
        $this->assertSame('The past card.', $rule->description_en);
        $this->assertSame($back->id, $rule->card_back_id);
        $this->assertSame(GameRule::INFO_MEANING, $rule->info_mode);
        $this->assertSame(1, $rule->draw_order);
    }

    public function test_draw_order_is_stored_and_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.games.store'), [
                'name_hu' => 'Sorrend',
                'name_en' => 'Order',
                'sort_order' => 6,
                'is_active' => '1',
                'rules' => [
                    [
                        'pack_type' => 'tarot',
                        'title_hu' => 'Később',
                        'title_en' => 'Later',
                        'draw_order' => 2,
                        'row' => 1,
                        'place' => 1,
                    ],
                    [
                        'pack_type' => 'oracle',
                        'title_hu' => 'Előbb',
                        'title_en' => 'Sooner',
                        'draw_order' => 1,
                        'row' => 2,
                        'place' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $rules = Game::query()->where('slug', 'sorrend')->firstOrFail()->rules()->orderBy('draw_order')->get();
        $this->assertSame(['Előbb', 'Később'], $rules->pluck('title_hu')->all());
        $this->assertSame([1, 2], $rules->pluck('draw_order')->all());

        $this->actingAs($admin)
            ->post(route('admin.games.store'), [
                'name_hu' => 'Ütköző',
                'name_en' => 'Clash',
                'sort_order' => 7,
                'is_active' => '1',
                'rules' => [
                    [
                        'pack_type' => 'tarot',
                        'title_hu' => 'A',
                        'title_en' => 'A',
                        'draw_order' => 1,
                        'row' => 1,
                        'place' => 1,
                    ],
                    [
                        'pack_type' => 'tarot',
                        'title_hu' => 'B',
                        'title_en' => 'B',
                        'draw_order' => 1,
                        'row' => 1,
                        'place' => 2,
                    ],
                ],
            ])
            ->assertSessionHasErrors('rules.1.draw_order');
    }
}
