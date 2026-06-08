<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:super_admin'])
            ->get('/test-super-only', fn () => response()->json(['ok' => true]));
    }

    public function test_super_admin_can_access(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user)->getJson('/test-super-only')->assertOk();
    }

    public function test_regular_admin_forbidden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/test-super-only')->assertForbidden();
    }

    public function test_unauthenticated_unauthorized(): void
    {
        $this->getJson('/test-super-only')->assertUnauthorized();
    }

    public function test_sales_user_cannot_create_invoice(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user)
            ->postJson('/api/v1/invoices', [])
            ->assertForbidden();
    }

    public function test_sales_user_cannot_delete_invoice(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user)
            ->deleteJson('/api/v1/invoices/1')
            ->assertForbidden();
    }

    public function test_sales_user_can_list_invoices(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user)
            ->getJson('/api/v1/invoices')
            ->assertOk();
    }

    public function test_sales_user_can_crud_customers(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user)
            ->getJson('/api/v1/customers')
            ->assertOk();
    }

    public function test_sales_user_cannot_access_audit(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user)
            ->getJson('/api/v1/audit/activity')
            ->assertForbidden();
    }

    public function test_sales_user_cannot_access_currency_rates_write(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user)
            ->putJson('/api/v1/currency-rates/USD', ['rate' => 1.0])
            ->assertForbidden();
    }
}
