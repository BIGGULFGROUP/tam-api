<?php

namespace Tests\Feature;

use App\Models\AdminProfile;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'admin.panel:backend'])
            ->get('/__test/backend', fn () => response()->json(['ok' => true]));

        Route::middleware(['auth:sanctum', 'admin.panel:frontend'])
            ->get('/__test/frontend', fn () => response()->json(['ok' => true]));

        Route::domain('backend-admin.test')
            ->get('/__test/domain', fn () => response()->json(['ok' => true]));
    }

    public function test_frontend_token_cannot_access_backend_panel_route(): void
    {
        $admin = new AdminProfile([
            'id' => '1ff4089b-7958-49d7-bf04-66727f321001',
            'email' => 'writer@example.com',
            'role' => 'writer',
            'access_tier' => 'frontend_admin',
            'is_active' => true,
            'can_access_frontend_panel' => true,
            'can_access_backend_panel' => false,
        ]);

        Sanctum::actingAs($admin, ['frontend:access']);

        $this->getJson('/__test/backend')
            ->assertForbidden()
            ->assertJsonPath('required_panel', 'backend');
    }

    public function test_backend_token_cannot_access_frontend_panel_route(): void
    {
        $admin = new AdminProfile([
            'id' => '1ff4089b-7958-49d7-bf04-66727f321002',
            'email' => 'support@example.com',
            'role' => 'support',
            'access_tier' => 'backend_admin',
            'is_active' => true,
            'can_access_frontend_panel' => false,
            'can_access_backend_panel' => true,
        ]);

        Sanctum::actingAs($admin, ['backend:access']);

        $this->getJson('/__test/frontend')
            ->assertForbidden()
            ->assertJsonPath('required_panel', 'frontend');
    }

    public function test_backend_domain_route_is_not_available_on_other_hosts(): void
    {
        $this->withHeader('host', 'frontend-admin.test')
            ->getJson('/__test/domain')
            ->assertNotFound();
    }
}
