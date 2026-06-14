<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->grantPermissions($this->user, [
            [PermissionGroup::AUDIT_LOG, PermissionAction::VIEW],
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_lists_audit_logs(): void
    {
        AuditLog::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/admin/audit-logs');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    public function test_filters_audit_logs_by_action(): void
    {
        AuditLog::factory()->create(['action' => 'create']);
        AuditLog::factory()->create(['action' => 'create']);
        AuditLog::factory()->create(['action' => 'delete']);

        $response = $this->getJson('/api/v1/admin/audit-logs?filter.action=create');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_filters_audit_logs_by_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        AuditLog::factory()->create(['actor_user_id' => $user1->id]);
        AuditLog::factory()->create(['actor_user_id' => $user1->id]);
        AuditLog::factory()->create(['actor_user_id' => $user2->id]);

        $response = $this->getJson("/api/v1/admin/audit-logs?filter.actorUserId={$user1->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_filters_audit_logs_by_date_range(): void
    {
        AuditLog::factory()->create(['at' => now()->subDays(10)]);
        AuditLog::factory()->create(['at' => now()->subDays(5)]);
        AuditLog::factory()->create(['at' => now()]);

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $response = $this->getJson("/api/v1/admin/audit-logs?filter.from={$from}&filter.to={$to}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_returns_audit_logs_with_actor_info(): void
    {
        $log = AuditLog::factory()->create();

        $response = $this->getJson('/api/v1/admin/audit-logs');

        $response->assertOk();
        $this->assertEquals($log->actor_user_id, $response->json('data.0.user.id'));
        $this->assertNotEmpty($response->json('data.0.user.name'));
        $this->assertEquals($log->action, $response->json('data.0.action'));
    }

    public function test_paginates_audit_logs(): void
    {
        AuditLog::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/admin/audit-logs?perPage=10');

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(25, $response->json('meta.total'));
    }
}
