<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_activate_template(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Template::factory()->active()->create();

        $this->actingAs($admin)
            ->post(route('admin.templates.store'), [
                'name' => 'Night',
                'slug' => 'night',
                'description' => 'Night theme',
            ])
            ->assertRedirect(route('admin.templates.index'));

        $night = Template::query()->where('slug', 'night')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.templates.activate', $night))
            ->assertRedirect(route('admin.templates.index'));

        $this->assertTrue($night->fresh()->is_active);
        $this->assertFalse($first->fresh()->is_active);
    }
}
