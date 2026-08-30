<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPackagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_edit_a_package(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk()
            ->assertSee('Premium', false);

        $this->actingAs($admin)
            ->get(route('admin.packages.create'))
            ->assertOk()
            ->assertSee(__('admin.packages.body_hu'), false)
            ->assertSee(__('admin.packages.perks'), false)
            ->assertSee(__('admin.packages.perks_how'), false)
            ->assertSee(__('admin.packages.price_usd'), false)
            ->assertSee(__('admin.packages.icon'), false);

        $this->actingAs($admin)
            ->post(route('admin.packages.store'), [
                'name_hu' => 'Arany',
                'name_en' => 'Gold',
                'slug' => 'gold',
                'price_huf' => 9900,
                'price_usd' => '29.99',
                'period' => 'yearly',
                'credits' => 200,
                'body_hu' => '<p>Éves csomag</p><script>alert(1)</script>',
                'body_en' => '<p>Yearly pack</p>',
                'is_active' => '1',
                'sort_order' => 4,
                'feature_keys' => ['reading.export.pdf', 'readings.history'],
            ])
            ->assertRedirect();

        $package = Package::query()->where('slug', 'gold')->firstOrFail();
        $this->assertSame('Arany', $package->name_hu);
        $this->assertSame(9900, $package->price_huf);
        $this->assertSame('29.99', (string) $package->price_usd);
        $this->assertSame('yearly', $package->period);
        $this->assertTrue($package->is_active);
        $this->assertTrue($package->can_export_pdf);
        $this->assertTrue(in_array('reading.export.pdf', $package->perk_keys, true));
        $this->assertStringContainsString('<p>Éves csomag</p>', (string) $package->body_hu);
        $this->assertStringContainsString('PDF mentés', (string) $package->body_hu);
        $this->assertStringContainsString('200 kredit / év', (string) $package->body_hu);
        $this->assertStringContainsString('PDF download', (string) $package->body_en);
        $this->assertStringContainsString('200 credits / year', (string) $package->body_en);
        $this->assertStringNotContainsString('<script>', (string) $package->body_hu);

        $this->actingAs($admin)
            ->get(route('admin.packages.edit', $package))
            ->assertOk()
            ->assertSee('Arany', false);

        $this->actingAs($admin)
            ->put(route('admin.packages.update', $package), [
                'name_hu' => 'Arany Plus',
                'name_en' => 'Gold Plus',
                'slug' => 'gold',
                'price_huf' => 10900,
                'price_usd' => '32.50',
                'period' => 'yearly',
                'credits' => 220,
                'body_hu' => '<p>Frissítve</p>',
                'body_en' => '<p>Updated</p>',
                'is_active' => '1',
                'sort_order' => 4,
                'feature_keys' => ['reading.export.pdf', 'readings.history'],
            ])
            ->assertRedirect(route('admin.packages.edit', $package));

        $this->assertSame('Arany Plus', $package->fresh()->name_hu);
        $this->assertSame(10900, (int) $package->fresh()->price_huf);
        $this->assertSame('32.50', (string) $package->fresh()->price_usd);
    }

    public function test_admin_can_deactivate_package_and_it_hides_from_catalog(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['plan' => 'free']);
        $package = Package::query()->where('slug', 'premium')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.packages.toggle', $package))
            ->assertRedirect(route('admin.packages.index'));

        $this->assertFalse($package->fresh()->is_active);

        $this->actingAs($user)
            ->get(route('credits.packages'))
            ->assertOk()
            ->assertDontSee('data-plan-card="premium"', false)
            ->assertSee('Expert', false);
    }

    public function test_non_admin_cannot_manage_packages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.packages.index'))
            ->assertForbidden();
    }

    public function test_public_catalog_renders_html_body_and_yearly_period(): void
    {
        Package::factory()->yearly()->create([
            'slug' => 'gold',
            'name_hu' => 'Arany',
            'name_en' => 'Gold',
            'price_huf' => 12000,
            'price_usd' => 36,
            'body_hu' => '<p>Saját HTML leírás</p>',
            'is_active' => true,
            'sort_order' => 9,
        ]);

        $user = User::factory()->create(['plan' => 'free']);

        $this->actingAs($user)
            ->withSession(['locale' => 'hu'])
            ->get(route('credits.packages'))
            ->assertOk()
            ->assertSee('Arany', false)
            ->assertSee('Saját HTML leírás', false)
            ->assertSee('12 000 Ft', false)
            ->assertSee('/'.__('messages.plan_per_year'), false);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('credits.packages'))
            ->assertOk()
            ->assertSee('Gold', false)
            ->assertSee('$36', false);
    }
}
