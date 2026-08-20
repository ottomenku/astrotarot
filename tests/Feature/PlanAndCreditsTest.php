<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanAndCreditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_packages_page_shows_plans_and_single_50_request(): void
    {
        $user = User::factory()->create(['credits' => 10, 'plan' => 'free']);

        $this->actingAs($user)
            ->get(route('credits.packages'))
            ->assertOk()
            ->assertSee('Premium', false)
            ->assertSee('Expert', false)
            ->assertSee('1 600', false)
            ->assertSee('2 500', false)
            ->assertSee('pack_50', false)
            ->assertDontSee('pack_10', false)
            ->assertDontSee('pack_30', false);
    }

    public function test_daily_login_bonus_once_per_day_for_free_users(): void
    {
        $user = User::factory()->create([
            'credits' => 10,
            'plan' => 'free',
            'last_daily_credit_at' => null,
        ]);

        $service = app(CreditService::class);
        $this->assertSame(11, $service->grantDailyLoginBonus($user->fresh()));
        $this->assertSame(11, $service->grantDailyLoginBonus($user->fresh()));
    }

    public function test_promo_activates_premium_with_monthly_credits(): void
    {
        $user = User::factory()->create(['credits' => 5, 'plan' => 'free']);

        $this->actingAs($user)
            ->postJson(route('credits.promo'), ['plan' => 'premium'])
            ->assertOk()
            ->assertJsonPath('plan', 'premium')
            ->assertJsonPath('credits', 55);

        $user->refresh();
        $this->assertSame('premium', $user->plan);
        $this->assertTrue($user->isPremiumOrAbove());
    }

    public function test_free_user_cannot_download_pdf(): void
    {
        $user = User::factory()->create(['credits' => 10, 'plan' => 'free']);

        $this->actingAs($user)
            ->postJson(route('reading.export.pdf'), [
                'question' => 'Mit tegyek most?',
                'locale' => 'hu',
                'cards' => [
                    ['figure_id' => 'mars', 'figure_name' => 'Mars', 'deck' => 'oracle', 'slot_title' => 'Mester'],
                    ['figure_id' => '01-the-magician', 'figure_name' => 'A Mágus', 'deck' => 'tarot', 'slot_title' => 'Figura'],
                ],
                'answers' => [['mode' => 'initial', 'label' => 'Olvasat', 'text' => 'Válasz']],
            ])
            ->assertForbidden();
    }

    public function test_credit_request_only_allows_pack_50(): void
    {
        $user = User::factory()->create(['credits' => 3, 'plan' => 'free']);

        $this->actingAs($user)
            ->post(route('credits.request'), ['package_key' => 'pack_10'])
            ->assertSessionHasErrors('package_key');

        $this->actingAs($user)
            ->post(route('credits.request'), ['package_key' => 'pack_50'])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_requests', [
            'user_id' => $user->id,
            'package_key' => 'pack_50',
            'credits' => 50,
        ]);
    }
}
