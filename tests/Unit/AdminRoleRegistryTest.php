<?php

namespace Tests\Unit;

use App\Support\AdminRoleRegistry;
use PHPUnit\Framework\TestCase;

class AdminRoleRegistryTest extends TestCase
{
    public function test_frontend_creator_roles_are_mapped_to_frontend_admin_tier(): void
    {
        $this->assertSame(AdminRoleRegistry::FRONTEND_ADMIN, AdminRoleRegistry::tierFor('guest_writer'));
        $this->assertTrue(AdminRoleRegistry::panelAccessFor('guest_writer')['frontend']);
        $this->assertFalse(AdminRoleRegistry::panelAccessFor('guest_writer')['backend']);
    }

    public function test_backend_staff_roles_are_mapped_to_backend_admin_tier(): void
    {
        $this->assertSame(AdminRoleRegistry::BACKEND_ADMIN, AdminRoleRegistry::tierFor('support'));
        $this->assertFalse(AdminRoleRegistry::panelAccessFor('support')['frontend']);
        $this->assertTrue(AdminRoleRegistry::panelAccessFor('support')['backend']);
    }

    public function test_super_admin_can_access_both_panels(): void
    {
        $this->assertSame(AdminRoleRegistry::BACKEND_ADMIN, AdminRoleRegistry::tierFor('super_admin'));
        $this->assertTrue(AdminRoleRegistry::panelAccessFor('super_admin')['frontend']);
        $this->assertTrue(AdminRoleRegistry::panelAccessFor('super_admin')['backend']);
        $this->assertTrue(AdminRoleRegistry::permissionsFor('super_admin')['manage_roles']);
    }
}
