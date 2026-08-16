<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\Report;
use App\Models\User;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('owner can view donors applicants and reports without assignable permissions', function () {
    [$owner] = organization_data_and_audit_test_owner();

    $this->actingAs($owner)->getJson('/api/v1/org/donors')->assertOk();
    $this->actingAs($owner)->getJson('/api/v1/org/applicants')->assertOk();
    $this->actingAs($owner)->getJson('/api/v1/org/reports')->assertOk();
});
test('report actions are restricted to same organization', function () {
    [$owner] = organization_data_and_audit_test_owner();
    $otherOrganization = Organization::factory()->create();
    $report = Report::factory()->create([
        'organization_id' => $otherOrganization->id,
        'status' => 'new',
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/org/reports/{$report->id}/claim")
        ->assertForbidden();
});
test('report status contract updates report lifecycle', function () {
    [$owner, $organization] = organization_data_and_audit_test_owner();
    $report = Report::factory()->create([
        'organization_id' => $organization->id,
        'status' => 'new',
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/v1/org/reports/{$report->id}/status", [
            'status' => 'in_progress',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->actingAs($owner)
        ->patchJson("/api/v1/org/reports/{$report->id}/status", [
            'status' => 'closed',
            'note' => 'Resolved by the organization team.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');
});
test('staff with audit permission can view scoped logs', function () {
    $organization = Organization::factory()->create();
    $staff = User::factory()->create(['organization_id' => $organization->id]);
    $otherOrganization = Organization::factory()->create();
    $otherActor = User::factory()->create(['organization_id' => $otherOrganization->id]);

    $this->grantPermissions($staff, [
        [PermissionGroup::ORG_AUDIT_LOG, PermissionAction::VIEW],
    ]);

    AuditLog::factory()->create(['actor_user_id' => $staff->id]);
    AuditLog::factory()->create(['actor_user_id' => $otherActor->id]);

    $this->actingAs($staff)
        ->getJson('/api/v1/org/audit-logs')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.id', $staff->id);
});
test('staff without audit permission is forbidden', function () {
    $organization = Organization::factory()->create();
    $staff = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($staff)
        ->getJson('/api/v1/org/audit-logs')
        ->assertForbidden();
});
test('audit logs are owner accessible and scoped to actor organization', function () {
    [$owner] = organization_data_and_audit_test_owner();
    [$otherOwner] = organization_data_and_audit_test_owner();

    AuditLog::factory()->create(['actor_user_id' => $owner->id]);
    AuditLog::factory()->create(['actor_user_id' => $otherOwner->id]);

    $this->actingAs($owner)
        ->getJson('/api/v1/org/audit-logs')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.id', $owner->id);
});
/** @return array{User, Organization} */
function organization_data_and_audit_test_owner(): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['organization_id' => $organization->id]);
    $role = OrganizationRole::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
        'is_system' => true,
    ]);

    OrganizationStaff::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
        'organization_role_id' => $role->id,
        'status' => 'active',
    ]);

    return [$owner, $organization];
}
