<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationDataAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_donors_applicants_and_reports_without_assignable_permissions(): void
    {
        [$owner] = $this->owner();

        $this->actingAs($owner)->getJson('/api/v1/org/donors')->assertOk();
        $this->actingAs($owner)->getJson('/api/v1/org/applicants')->assertOk();
        $this->actingAs($owner)->getJson('/api/v1/org/reports')->assertOk();
    }

    public function test_report_actions_are_restricted_to_same_organization(): void
    {
        [$owner] = $this->owner();
        $otherOrganization = Organization::factory()->create();
        $report = Report::factory()->create([
            'organization_id' => $otherOrganization->id,
            'status' => 'new',
        ]);

        $this->actingAs($owner)
            ->postJson("/api/v1/org/reports/{$report->id}/claim")
            ->assertForbidden();
    }

    public function test_audit_logs_are_owner_only_and_scoped_to_actor_organization(): void
    {
        [$owner] = $this->owner();
        [$otherOwner] = $this->owner();

        AuditLog::factory()->create(['actor_user_id' => $owner->id]);
        AuditLog::factory()->create(['actor_user_id' => $otherOwner->id]);

        $this->actingAs($owner)
            ->getJson('/api/v1/org/audit-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actorUserId', (string) $owner->id);
    }

    /** @return array{User, Organization} */
    private function owner(): array
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
}
