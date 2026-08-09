<?php

namespace Tests\Unit;

use App\Enums\Role;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    public function test_role_values_include_expected_levels(): void
    {
        $this->assertSame(
            ['user', 'pro', 'expert', 'admin'],
            Role::values()
        );

        $this->assertTrue(Role::Admin->isAdmin());
        $this->assertFalse(Role::Expert->isAdmin());
    }
}
