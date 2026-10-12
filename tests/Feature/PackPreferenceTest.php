<?php

namespace Tests\Feature;

use App\Models\CardPack;
use App\Models\Game;
use App\Models\User;
use App\Models\UserPackPreference;
use App\Services\GameConfigService;
use App\Services\PackResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_user_sees_pack_picker_but_cannot_save_choices(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $cigany = CardPack::query()->where('slug', 'cigany-kartya')->firstOrFail();

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee(__('messages.account_packs'), false)
            ->assertSee(__('messages.account_packs_locked'), false);

        $this->actingAs($user)
            ->post(route('account.packs.update'), [
                'figures' => [
                    CardPack::TYPE_TAROT => $cigany->id,
                ],
            ])
            ->assertRedirect(route('account.show'));

        $this->assertDatabaseMissing('user_pack_preferences', [
            'user_id' => $user->id,
        ]);
    }

    public function test_premium_user_can_save_and_clear_pack_choices(): void
    {
        $user = User::factory()->create(['plan' => 'premium']);
        $cigany = CardPack::query()->where('slug', 'cigany-kartya')->firstOrFail();
        $back = CardPack::factory()->cardBack()->create();

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee(__('messages.account_packs_save'), false)
            ->assertSee(__('messages.account_packs_back_preview'), false)
            ->assertSee('name="card_back"', false)
            ->assertDontSee('name="backs[', false);

        $this->actingAs($user)
            ->post(route('account.packs.update'), [
                'figures' => [
                    CardPack::TYPE_TAROT => $cigany->id,
                ],
                'card_back' => $back->id,
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status', __('messages.account_packs_saved'));

        $this->assertDatabaseHas('user_pack_preferences', [
            'user_id' => $user->id,
            'role' => UserPackPreference::ROLE_FIGURE,
            'pack_type' => CardPack::TYPE_TAROT,
            'card_pack_id' => $cigany->id,
        ]);
        $this->assertDatabaseHas('user_pack_preferences', [
            'user_id' => $user->id,
            'role' => UserPackPreference::ROLE_CARD_BACK,
            'figure_pack_id' => null,
            'card_pack_id' => $back->id,
        ]);

        $this->actingAs($user);
        $packs = app(PackResolutionService::class);
        $tarot = CardPack::query()->where('slug', 'tarot')->firstOrFail();
        $oracle = CardPack::query()->where('slug', 'egi-orakulum')->firstOrFail();
        $this->assertSame($back->fileUrl(), $packs->skin($user, $tarot)['card_back']);
        $this->assertSame($back->fileUrl(), $packs->skin($user, $oracle)['card_back']);

        $this->actingAs($user)
            ->post(route('account.packs.update'), [
                'figures' => [
                    CardPack::TYPE_TAROT => '',
                ],
                'card_back' => '',
            ])
            ->assertRedirect(route('account.show'));

        $this->assertDatabaseMissing('user_pack_preferences', [
            'user_id' => $user->id,
        ]);
    }

    public function test_game_config_uses_game_default_then_user_premium_choice(): void
    {
        $game = Game::query()->where('slug', 'astro-tarot')->with(['rules.defaultPack'])->firstOrFail();
        $service = app(GameConfigService::class);

        $guest = $service->toArray($game, 'hu');
        $this->assertSame('egi-orakulum', $guest['slots'][0]['pack_slug']);
        $this->assertSame('oracle', $guest['slots'][0]['pack_type']);
        $this->assertSame('tarot', $guest['slots'][1]['pack_slug']);
        $this->assertFalse($guest['can_choose_packs']);

        $free = User::factory()->create(['plan' => 'free']);
        $this->actingAs($free);
        $freeConfig = $service->toArray($game, 'hu');
        $this->assertSame('tarot', $freeConfig['slots'][1]['pack_slug']);
        $this->assertFalse($freeConfig['can_choose_packs']);

        $premium = User::factory()->create(['plan' => 'premium']);
        $cigany = CardPack::query()->where('slug', 'cigany-kartya')->firstOrFail();
        UserPackPreference::query()->create([
            'user_id' => $premium->id,
            'role' => UserPackPreference::ROLE_FIGURE,
            'pack_type' => CardPack::TYPE_TAROT,
            'card_pack_id' => $cigany->id,
        ]);

        $this->actingAs($premium);
        $chosen = $service->toArray($game, 'hu');
        $this->assertTrue($chosen['can_choose_packs']);
        $this->assertSame('egi-orakulum', $chosen['slots'][0]['pack_slug']);
        $this->assertSame('cigany-kartya', $chosen['slots'][1]['pack_slug']);
        $this->assertArrayHasKey('card_back', $chosen['packs']['cigany-kartya']);
    }

    public function test_allowed_deck_blocks_pack_spoofing_without_premium(): void
    {
        $game = Game::query()->where('slug', 'astro-tarot')->with(['rules.defaultPack'])->firstOrFail();
        $packs = app(PackResolutionService::class);

        $free = User::factory()->create(['plan' => 'free']);
        $this->assertSame('tarot', $packs->allowedDeck($free, $game, 1, 'cigany-kartya'));
        $this->assertSame('egi-orakulum', $packs->allowedDeck($free, $game, 0, 'tarot'));

        $premium = User::factory()->create(['plan' => 'premium']);
        $this->assertSame('cigany-kartya', $packs->allowedDeck($premium, $game, 1, 'cigany-kartya'));
        $this->assertSame('tarot', $packs->allowedDeck($premium, $game, 1, 'egi-orakulum'));
    }
}
