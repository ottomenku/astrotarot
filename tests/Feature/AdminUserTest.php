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
}
