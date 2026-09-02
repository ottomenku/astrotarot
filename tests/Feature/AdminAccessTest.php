<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin(): void
    {
        $this->get(route('admin.ephemeris.index'))->assertRedirect(route('login'));
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->get(route('admin.templates.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_admin(): void
    {
        $user = User::factory()->role(Role::Pro)->create();

        $this->actingAs($user)
            ->get(route('admin.ephemeris.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.templates.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.legal.edit'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.deletion-requests.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.games.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.packs.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.feature-access.edit'))
            ->assertOk();
    }
}
