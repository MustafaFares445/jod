<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\AuditLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->grantPermissions($this->user, [
        [PermissionGroup::AUDIT_LOG, PermissionAction::VIEW],
    ]);
    Sanctum::actingAs($this->user);
});
test('lists audit logs', function () {
    AuditLog::factory()->count(5)->create();

    $response = $this->getJson('/api/v1/admin/audit-logs');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(5);
});
test('filters audit logs by action', function () {
    AuditLog::factory()->create(['action' => 'create']);
    AuditLog::factory()->create(['action' => 'create']);
    AuditLog::factory()->create(['action' => 'delete']);

    $response = $this->getJson('/api/v1/admin/audit-logs?filter.action=create');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});
test('filters audit logs by user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    AuditLog::factory()->create(['actor_user_id' => $user1->id]);
    AuditLog::factory()->create(['actor_user_id' => $user1->id]);
    AuditLog::factory()->create(['actor_user_id' => $user2->id]);

    $response = $this->getJson("/api/v1/admin/audit-logs?filter.actorUserId={$user1->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});
test('filters audit logs by date range', function () {
    AuditLog::factory()->create(['at' => now()->subDays(10)]);
    AuditLog::factory()->create(['at' => now()->subDays(5)]);
    AuditLog::factory()->create(['at' => now()]);

    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $response = $this->getJson("/api/v1/admin/audit-logs?filter.from={$from}&filter.to={$to}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});
test('returns audit logs with actor info', function () {
    $log = AuditLog::factory()->create();

    $response = $this->getJson('/api/v1/admin/audit-logs');

    $response->assertOk();
    expect($response->json('data.0.user.id'))->toEqual($log->actor_user_id);
    expect($response->json('data.0.user.name'))->not->toBeEmpty();
    expect($response->json('data.0.action'))->toEqual($log->action);
});
test('paginates audit logs', function () {
    AuditLog::factory()->count(25)->create();

    $response = $this->getJson('/api/v1/admin/audit-logs?perPage=10');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('meta.total'))->toEqual(25);
});
