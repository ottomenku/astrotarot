<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_with_role(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Expert User',
                'email' => 'expert@example.com',
                'password' => 'password',
                'role' => Role::Expert->value,
                'template_id' => $template->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'expert@example.com',
            'role' => Role::Expert->value,
            'template_id' => $template->id,
        ]);
    }

    public function test_admin_can_update_user_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->role(Role::User)->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => Role::Pro->value,
                'template_id' => null,
            ])
            ->assertRedirect(route('admin.users.edit', $user));

        $this->assertSame(Role::Pro, $user->fresh()->role);
    }

    public function test_user_list_uses_icons_and_hides_template(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create(['name' => 'ListaSablonXYZ']);
        $user = User::factory()->create([
            'name' => 'Lista Elek',
            'credits' => 4,
            'template_id' => $template->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Lista Elek', false)
            ->assertDontSee('ListaSablonXYZ', false)
            ->assertDontSee(__('admin.nav.slots'), false)
            ->assertDontSee(__('admin.users.edit'), false)
            ->assertSee(route('admin.users.edit', $user), false);
    }

    public function test_admin_can_grant_credits(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['credits' => 3]);

        $this->actingAs($admin)
            ->post(route('admin.users.credits', $user), ['amount' => 10])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame(13, (int) $user->fresh()->credits);
    }

    public function test_admin_can_ban_user_and_banned_user_cannot_login(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'email' => 'tiltott@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.ban', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNotNull($user->fresh()->banned_at);

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => 'tiltott@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_cannot_ban_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.ban', $admin))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('user');

        $this->assertNull($admin->fresh()->banned_at);
    }
}
