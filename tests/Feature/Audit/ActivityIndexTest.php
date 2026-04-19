<?php

namespace Tests\Feature\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_admin_can_view(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->getJson('/api/v1/audit/activity')->assertOk();
    }

    public function test_unauth_denied(): void
    {
        $this->getJson('/api/v1/audit/activity')->assertUnauthorized();
    }

    public function test_filter_by_action(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        ActivityLog::create(['action' => 'customer.created', 'user_id' => $u->id, 'properties' => []]);
        ActivityLog::create(['action' => 'invoice.created', 'user_id' => $u->id, 'properties' => []]);

        $res = $this->getJson('/api/v1/audit/activity?action=customer.created')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('customer.created', $res->json('data.0.action'));
    }

    public function test_filter_by_user_id(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        ActivityLog::create(['action' => 'x', 'user_id' => $alice->id, 'properties' => []]);
        ActivityLog::create(['action' => 'x', 'user_id' => $bob->id, 'properties' => []]);

        $res = $this->actingAs($alice)->getJson("/api/v1/audit/activity?user_id={$alice->id}")->assertOk();
        foreach ($res->json('data') as $row) {
            if ($row['user']) {
                $this->assertSame($alice->id, $row['user']['id']);
            }
        }
    }
}
