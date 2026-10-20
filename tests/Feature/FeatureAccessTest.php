<?php

namespace Tests\Feature;

use App\Models\FeatureAccess;
use App\Models\Package;
use App\Models\Template;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CardDrawProfileService;
use App\Support\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_user_can_download_pdf_when_it_is_open_to_every_package(): void
    {
        $user = User::factory()->create(['credits' => 10, 'plan' => 'free']);

        $rule = FeatureAccess::query()->where('key', FeatureCatalog::PDF)->firstOrFail();
        $rule->update([
            'credit_cost' => 0,
            'logic' => FeatureCatalog::LOGIC_AND,
        ]);
        $rule->packages()->sync([]);

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
            ->assertOk();
    }

    public function test_or_rule_lets_free_user_pay_credits_instead_of_package(): void
    {
        $premiumId = Package::query()->where('slug', 'premium')->value('id');
        $expertId = Package::query()->where('slug', 'expert')->value('id');
        $rule = FeatureAccess::query()->where('key', FeatureCatalog::PDF)->firstOrFail();
        $rule->update([
            'credit_cost' => 2,
            'logic' => FeatureCatalog::LOGIC_OR,
        ]);
        $rule->packages()->sync(array_filter([$premiumId, $expertId]));

        $free = User::factory()->create(['credits' => 5, 'plan' => 'free']);
        $premium = User::factory()->create(['credits' => 5, 'plan' => 'premium']);

        $payload = [
            'question' => 'Mit tegyek most?',
            'locale' => 'hu',
            'cards' => [
                ['figure_id' => 'mars', 'figure_name' => 'Mars', 'deck' => 'oracle', 'slot_title' => 'Mester'],
                ['figure_id' => '01-the-magician', 'figure_name' => 'A Mágus', 'deck' => 'tarot', 'slot_title' => 'Figura'],
            ],
            'answers' => [['mode' => 'initial', 'label' => 'Olvasat', 'text' => 'Válasz']],
        ];

        $this->actingAs($free)
            ->post(route('reading.export.pdf'), $payload)
            ->assertOk();
        $this->assertSame(3, (int) $free->fresh()->credits);

        $this->actingAs($premium)
            ->post(route('reading.export.pdf'), $payload)
            ->assertOk();
        $this->assertSame(5, (int) $premium->fresh()->credits);
    }

    public function test_history_stays_locked_for_free_and_open_for_premium(): void
    {
        $free = User::factory()->create(['plan' => 'free']);
        $premium = User::factory()->create(['plan' => 'premium']);

        $this->actingAs($free)
            ->get(route('readings.history'))
            ->assertForbidden();

        $this->actingAs($premium)
            ->get(route('readings.history'))
            ->assertOk();
    }

    public function test_admin_bypasses_package_and_credit_gates(): void
    {
        $admin = User::factory()->admin()->create(['credits' => 0, 'plan' => 'free']);

        $this->actingAs($admin)
            ->get(route('readings.history'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('reading.export.pdf'), [
                'question' => 'Mit tegyek most?',
                'locale' => 'hu',
                'cards' => [
                    ['figure_id' => 'mars', 'figure_name' => 'Mars', 'deck' => 'oracle', 'slot_title' => 'Mester'],
                    ['figure_id' => '01-the-magician', 'figure_name' => 'A Mágus', 'deck' => 'tarot', 'slot_title' => 'Figura'],
                ],
                'answers' => [['mode' => 'initial', 'label' => 'Olvasat', 'text' => 'Válasz']],
            ])
            ->assertOk();

        $this->assertSame(0, (int) $admin->fresh()->credits);
    }

    public function test_reading_uses_configured_credit_cost(): void
    {
        config([
            'services.openai.driver' => 'openai',
            'services.openai.api_key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Teszt olvasat.']],
                ],
            ], 200),
        ]);

        FeatureAccess::query()->where('key', FeatureCatalog::READING)->update(['credit_cost' => 2]);

        $user = User::factory()->create(['credits' => 5, 'plan' => 'free']);

        $this->actingAs($user)
            ->postJson(route('reading'), [
                'question' => 'Mit tegyek most a kapcsolatommal?',
                'locale' => 'hu',
                'cards' => [
                    [
                        'figure_id' => 'mars',
                        'figure_name' => 'Mars',
                        'deck' => 'oracle',
                    ],
                    [
                        'figure_id' => '01-the-magician',
                        'figure_name' => 'A Mágus',
                        'deck' => 'tarot',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('credits', 3);

        $this->assertSame(3, (int) $user->fresh()->credits);
    }

    public function test_home_shows_package_icons_and_credit_cost_on_controls(): void
    {
        Template::factory()->create([
            'name' => 'Art Deco',
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->mock(CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->andReturn([
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'fő',
                'tone' => 'pozitív',
                'variant' => 'positive',
                'tones' => ['pozitív', 'negatív', 'pozitív'],
                'variants' => ['positive', 'shadow', 'positive'],
                'slots' => ['leo', 'fire', 'cardinal'],
                'chart_mode' => 'now',
                'datetime_utc' => '2026-08-10T12:00:00Z',
            ]);
        });

        $user = User::factory()->create(['credits' => 5, 'plan' => 'free']);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('pkg-icon-heart', false)
            ->assertDontSee('pkg-icon-crown', false)
            ->assertSee('✦1', false)
            ->assertSee('data-share-pdf', false);
    }

    public function test_feature_mark_uses_the_cheapest_granting_package(): void
    {
        $marks = app(\App\Services\FeatureAccessService::class);

        $pdf = $marks->marks(FeatureCatalog::PDF);
        $this->assertSame(['premium'], array_column($pdf['packages'], 'slug'));

        $motto = $marks->marks(FeatureCatalog::ASTROMOTTO);
        $this->assertSame(['expert'], array_column($motto['packages'], 'slug'));
    }

    public function test_disabling_paid_features_opens_every_feature_and_hides_commerce(): void
    {
        app(AppSettings::class)->putMany([
            AppSettings::PAID_FEATURES_ENABLED => '0',
        ]);

        Template::factory()->create([
            'name' => 'Art Deco',
            'slug' => 'art-deco',
            'is_active' => true,
        ]);

        $this->mock(CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->andReturn([
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'fő',
                'tone' => 'pozitív',
                'variant' => 'positive',
                'tones' => ['pozitív', 'negatív', 'pozitív'],
                'variants' => ['positive', 'shadow', 'positive'],
                'slots' => ['leo', 'fire', 'cardinal'],
                'chart_mode' => 'now',
                'datetime_utc' => '2026-08-10T12:00:00Z',
            ]);
        });

        $free = User::factory()->create(['plan' => 'free', 'credits' => 0]);
        \App\Models\CreditRequest::query()->create([
            'user_id' => $free->id,
            'package_key' => 'credits_50',
            'credits' => 50,
            'status' => 'pending',
        ]);

        $this->actingAs($free)
            ->get(route('readings.history'))
            ->assertOk()
            ->assertDontSee(__('messages.credits_menu'), false);

        $this->actingAs($free)
            ->get(route('credits.packages'))
            ->assertRedirect(route('home'));

        $this->actingAs($free)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(__('messages.credits_menu'), false)
            ->assertDontSee('credits-chip', false)
            ->assertDontSee('pkg-icon-heart', false)
            ->assertDontSee('✦1', false);

        $this->actingAs($free)
            ->get(route('account.show'))
            ->assertOk()
            ->assertDontSee(__('messages.account_field_plan'), false)
            ->assertDontSee(__('messages.account_field_credits'), false)
            ->assertDontSee(__('messages.account_choose_plan'), false)
            ->assertDontSee(__('messages.account_buy_credits'), false)
            ->assertDontSee('credits_50', false)
            ->assertSee(__('messages.account_packs_lead_open'), false);
    }
}
