<?php

namespace Tests\Feature;

use App\Models\FeatureAccess;
use App\Models\Package;
use App\Models\Template;
use App\Models\User;
use App\Services\CardDrawProfileService;
use App\Support\FeatureCatalog;
use App\Support\PackageBody;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, array<string, mixed>>  $overrides
     * @return array{features: array<string, array<string, mixed>>}
     */
    private function accessPayload(array $overrides = []): array
    {
        $features = [];
        foreach (FeatureAccess::query()->with('packages')->get() as $rule) {
            $features[$rule->key] = [
                'label_hu' => $rule->label('hu'),
                'label_en' => $rule->label('en'),
                'credit_cost' => $rule->credit_cost,
                'logic' => $rule->logic,
                'package_ids' => $rule->packages->pluck('id')->all(),
            ];
        }

        foreach ($overrides as $key => $row) {
            $features[$key] = array_merge($features[$key] ?? [
                'label_hu' => PackageBody::perkLabel($key, 'hu'),
                'label_en' => PackageBody::perkLabel($key, 'en'),
                'credit_cost' => 0,
                'logic' => FeatureCatalog::LOGIC_AND,
                'package_ids' => [],
            ], $row);
        }

        return ['features' => $features];
    }

    public function test_admin_can_view_and_save_access_matrix(): void
    {
        $admin = User::factory()->admin()->create();
        $freeId = Package::query()->where('slug', 'free')->value('id');

        $this->actingAs($admin)
            ->get(route('admin.feature-access.edit'))
            ->assertOk()
            ->assertSee(FeatureCatalog::help(FeatureCatalog::READING), false)
            ->assertSee(FeatureCatalog::help(FeatureCatalog::PDF), false)
            ->assertSee(FeatureCatalog::help(FeatureCatalog::PACKS_CHOOSE), false)
            ->assertSee(__('admin.feature_access.card_name'), false);

        $this->actingAs($admin)
            ->put(
                route('admin.feature-access.update'),
                $this->accessPayload([
                    FeatureCatalog::PDF => [
                        'credit_cost' => 0,
                        'logic' => FeatureCatalog::LOGIC_AND,
                        'package_ids' => [$freeId],
                    ],
                ])
            )
            ->assertRedirect(route('admin.feature-access.edit'));

        $rule = FeatureAccess::query()->where('key', FeatureCatalog::PDF)->firstOrFail();
        $this->assertEqualsCanonicalizing([(int) $freeId], $rule->packages()->pluck('packages.id')->all());
    }

    public function test_non_admin_cannot_edit_access_matrix(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.feature-access.edit'))
            ->assertForbidden();
    }

    public function test_free_user_can_download_pdf_when_admin_opens_it(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['credits' => 10, 'plan' => 'free']);

        $this->actingAs($admin)
            ->put(
                route('admin.feature-access.update'),
                $this->accessPayload([
                    FeatureCatalog::PDF => [
                        'credit_cost' => 0,
                        'logic' => FeatureCatalog::LOGIC_AND,
                        'package_ids' => [],
                    ],
                ])
            )
            ->assertRedirect();

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
                        'background_id' => 'shared-light',
                        'deck' => 'oracle',
                    ],
                    [
                        'figure_id' => '01-the-magician',
                        'figure_name' => 'A Mágus',
                        'background_id' => 'shared-shadow',
                        'deck' => 'tarot',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('credits', 3);

        $this->assertSame(3, (int) $user->fresh()->credits);
    }

    public function test_custom_feature_label_appears_on_package_card(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['plan' => 'free']);

        $this->actingAs($admin)
            ->put(
                route('admin.feature-access.update'),
                $this->accessPayload([
                    FeatureCatalog::PDF => [
                        'label_hu' => 'PDF kártya',
                        'label_en' => 'PDF card',
                    ],
                ])
            )
            ->assertRedirect(route('admin.feature-access.edit'));

        $premium = Package::query()->where('slug', 'premium')->firstOrFail();
        $this->assertStringContainsString('PDF kártya', (string) $premium->body_hu);
        $this->assertStringContainsString('PDF card', (string) $premium->body_en);

        $this->actingAs($user)
            ->withSession(['locale' => 'hu'])
            ->get(route('credits.packages'))
            ->assertOk()
            ->assertSee('PDF kártya', false);
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
            ->assertSee('pkg-icon-crown', false)
            ->assertSee('✦1', false)
            ->assertSee('data-share-pdf', false);
    }
}
