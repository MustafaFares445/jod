<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Donation;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationWorkspaceEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::query()->create([
            'name' => 'Org One',
            'email' => 'org1@example.com',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->grantPermissions($this->user, [
            [PermissionGroup::ORG_CAMPAIGN, PermissionAction::VIEW],
            [PermissionGroup::ORG_CAMPAIGN, PermissionAction::CREATE],
            [PermissionGroup::ORG_CAMPAIGN, PermissionAction::UPDATE],
            [PermissionGroup::ORG_CAMPAIGN, PermissionAction::DELETE],
            [PermissionGroup::ORG_CAMPAIGN, PermissionAction::CLOSE],
            [PermissionGroup::ORG_POST, PermissionAction::VIEW],
            [PermissionGroup::ORG_POST, PermissionAction::CREATE],
            [PermissionGroup::ORG_POST, PermissionAction::UPDATE],
            [PermissionGroup::ORG_POST, PermissionAction::DELETE],
            [PermissionGroup::ORG_POST, PermissionAction::PUBLISH],
            [PermissionGroup::ORG_POST, PermissionAction::ARCHIVE],
            [PermissionGroup::ORG_POST, PermissionAction::RESTORE],
            [PermissionGroup::ORG_DONOR, PermissionAction::VIEW],
            [PermissionGroup::ORG_DONOR, PermissionAction::CREATE],
            [PermissionGroup::ORG_DONOR, PermissionAction::UPDATE],
            [PermissionGroup::ORG_DONOR, PermissionAction::DELETE],
            [PermissionGroup::ORG_APPLICANT, PermissionAction::VIEW],
            [PermissionGroup::ORG_APPLICANT, PermissionAction::CREATE],
            [PermissionGroup::ORG_APPLICANT, PermissionAction::UPDATE],
            [PermissionGroup::ORG_APPLICANT, PermissionAction::DELETE],
            [PermissionGroup::ORG_NOTIFICATION, PermissionAction::VIEW],
            [PermissionGroup::ORG_NOTIFICATION, PermissionAction::CREATE],
            [PermissionGroup::ORG_NOTIFICATION, PermissionAction::UPDATE],
            [PermissionGroup::ORG_NOTIFICATION, PermissionAction::DELETE],
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_campaign_close_rejects_invalid_state_transition(): void
    {
        $campaign = Campaign::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Draft Campaign',
            'summary' => 'Summary',
            'category' => 'health',
            'status' => 'draft',
            'goal_amount' => 1000,
            'beneficiaries_count' => 2,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ]);

        $response = $this->postJson("/api/v1/org/campaigns/{$campaign->id}/close", [
            'reason' => 'Closing draft campaign',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_post_publish_archive_restore_transitions(): void
    {
        $post = Post::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Org Post',
            'summary' => 'Summary',
            'type' => 'general',
            'status' => 'draft',
            'author_name' => 'Author',
            'location' => 'Riyadh',
        ]);

        $this->postJson("/api/v1/org/posts/{$post->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->postJson("/api/v1/org/posts/{$post->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->postJson("/api/v1/org/posts/{$post->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_donor_crud_and_filtering(): void
    {
        Donation::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Donor A',
            'email' => 'a@example.com',
            'campaign_title' => 'Health Initiative',
            'amount_or_type' => '500',
            'donated_at' => now()->subDay(),
            'city' => 'Riyadh',
            'created_by' => $this->user->id,
        ]);

        Donation::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Donor B',
            'email' => 'b@example.com',
            'campaign_title' => 'Food Initiative',
            'amount_or_type' => 'تبرع عيني',
            'donated_at' => now(),
            'city' => 'Jeddah',
            'created_by' => $this->user->id,
        ]);

        $this->getJson('/api/v1/org/donors?filter.city=Riyadh')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $createPayload = [
            'name' => 'Donor C',
            'email' => 'c@example.com',
            'phone' => '+966500000000',
            'campaignTitle' => 'Health Initiative',
            'amountOrType' => '1200',
            'donatedAt' => now()->toIso8601String(),
            'city' => 'Riyadh',
        ];

        $created = $this->postJson('/api/v1/org/donors', $createPayload)
            ->assertCreated()
            ->json('data.id');

        $this->patchJson("/api/v1/org/donors/{$created}", [
            ...$createPayload,
            'name' => 'Donor C Updated',
        ])->assertOk()->assertJsonPath('data.name', 'Donor C Updated');

        $this->deleteJson("/api/v1/org/donors/{$created}")->assertNoContent();
    }

    public function test_applicant_filtering_and_crud(): void
    {
        CampaignApplication::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Applicant A',
            'email' => 'aa@example.com',
            'campaign_title' => 'Volunteer Program',
            'applicant_status' => 'approved',
            'applied_at' => now(),
            'created_by' => $this->user->id,
        ]);

        CampaignApplication::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Applicant B',
            'email' => 'bb@example.com',
            'campaign_title' => 'Volunteer Program',
            'applicant_status' => 'pending',
            'applied_at' => now()->subDay(),
            'created_by' => $this->user->id,
        ]);

        $this->getJson('/api/v1/org/applicants?filter.applicantStatus=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $payload = [
            'name' => 'Applicant C',
            'email' => 'cc@example.com',
            'phone' => '+966500000001',
            'campaignTitle' => 'Volunteer Program',
            'applicantStatus' => 'under_review',
            'appliedAt' => now()->toIso8601String(),
            'requestType' => 'field_volunteer',
        ];

        $created = $this->postJson('/api/v1/org/applicants', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->patchJson("/api/v1/org/applicants/{$created}", [
            ...$payload,
            'applicantStatus' => 'accepted',
        ])->assertOk()->assertJsonPath('data.applicantStatus', 'accepted');

        $this->deleteJson("/api/v1/org/applicants/{$created}")->assertNoContent();
    }

    public function test_org_notifications_read_state_flow(): void
    {
        $notification = Notification::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'New donor',
            'body' => 'A new donor has been added.',
            'mailbox' => 'inbox',
            'status' => 'unread',
            'category' => 'donation',
            'recipient_scope' => 'organizations',
            'priority' => 'normal',
        ]);

        $this->patchJson("/api/v1/org/notifications/{$notification->id}/read-state", [
            'status' => 'read',
        ])->assertOk()->assertJsonPath('data.status', 'read');

        $this->patchJson("/api/v1/org/notifications/{$notification->id}/read-state", [
            'status' => 'unread',
        ])->assertOk()->assertJsonPath('data.status', 'unread');
    }
}
