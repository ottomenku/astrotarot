<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Template::query()->update(['is_active' => false]);

        $artDeco = Template::query()->updateOrCreate(
            ['slug' => 'art-deco'],
            [
                'name' => 'Art Deco',
                'description' => 'Astro Tarot Celestial Art Deco – 22 major arcana',
                'is_active' => true,
            ]
        );

        Template::query()->firstOrCreate(
            ['slug' => 'classic'],
            [
                'name' => 'Classic',
                'description' => 'Default classic template',
                'is_active' => false,
            ]
        );

        if ($this->importAstroAdmins($artDeco->id)) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => Role::Admin,
                'template_id' => $artDeco->id,
                'email_verified_at' => now(),
            ]
        );
    }

    private function importAstroAdmins(int $templateId): bool
    {
        try {
            $admins = DB::select(
                'SELECT name, email, password, email_verified_at, remember_token
                 FROM vs_cline.users
                 WHERE is_admin = 1
                 ORDER BY id'
            );
        } catch (\Throwable) {
            return false;
        }

        if ($admins === []) {
            return false;
        }

        foreach ($admins as $admin) {
            DB::table('users')->updateOrInsert(
                ['email' => $admin->email],
                [
                    'name' => $admin->name ?: 'Admin',
                    'password' => $admin->password,
                    'role' => Role::Admin->value,
                    'template_id' => $templateId,
                    'email_verified_at' => $admin->email_verified_at,
                    'remember_token' => $admin->remember_token,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return true;
    }
}
