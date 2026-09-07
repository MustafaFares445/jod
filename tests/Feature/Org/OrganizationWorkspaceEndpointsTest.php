<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Donation;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
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
});
test('campaign close rejects invalid state transition', function () {
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
});
test('post publish archive restore transitions', function () {
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

    $this->patchJson("/api/v1/org/posts/{$post->id}/status", ['status' => 'draft'])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');
});
test('donor crud and filtering', function () {
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
        'phone' => '0912345678',
        'city' => 'دمشق',
    ];

    $created = $this->postJson('/api/v1/org/donors', $createPayload)
        ->assertCreated()
        ->json('data.id');

    $this->patchJson("/api/v1/org/donors/{$created}", [
        ...$createPayload,
        'name' => 'Donor C Updated',
    ])->assertOk()->assertJsonPath('data.name', 'Donor C Updated');

    $this->deleteJson("/api/v1/org/donors/{$created}")
        ->assertOk()
        ->assertJsonPath('message', 'Data deleted successfully.');
});

test('organization completes campaign donation workflow using the confirmed received amount', function () {
    $donor = User::factory()->create();
    $campaign = Campaign::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'active',
        'goal_amount' => 1000,
        'raised_amount' => 100,
        'donors_count' => 3,
    ]);
    $donation = Donation::factory()->create([
        'organization_id' => $this->organization->id,
        'campaign_id' => $campaign->id,
        'campaign_title' => $campaign->title,
        'amount_or_type' => '50.00',
        'status' => 'pending',
        'source' => 'mobile_app',
        'created_by' => $donor->id,
    ]);

    $this->patchJson("/api/v1/org/donations/{$donation->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('data.acceptedAt', fn ($value) => filled($value));

    $this->patchJson("/api/v1/org/donations/{$donation->id}/contact")
        ->assertOk()
        ->assertJsonPath('data.status', 'contacting');

    $this->patchJson("/api/v1/org/donations/{$donation->id}/agree")
        ->assertOk()
        ->assertJsonPath('data.status', 'agreed');

    $this->patchJson("/api/v1/org/donations/{$donation->id}/complete", ['amount' => 40.25])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.requestedAmount', 50)
        ->assertJsonPath('data.confirmedAmount', 40.25)
        ->assertJsonPath('data.amount', 40.25);

    $this->assertDatabaseHas('donations', [
        'id' => $donation->id,
        'status' => 'completed',
        'confirmed_amount' => '40.25',
    ]);

    $campaign->refresh();
    expect((float) $campaign->raised_amount)->toBe(140.25);
    expect($campaign->donors_count)->toBe(4);
});

test('donor list filters organization donations by campaign and workflow status', function () {
    $campaign = Campaign::factory()->create(['organization_id' => $this->organization->id]);
    $otherCampaign = Campaign::factory()->create(['organization_id' => $this->organization->id]);

    Donation::factory()->create([
        'organization_id' => $this->organization->id,
        'campaign_id' => $campaign->id,
        'campaign_title' => $campaign->title,
        'status' => 'contacting',
        'created_by' => $this->user->id,
    ]);
    Donation::factory()->create([
        'organization_id' => $this->organization->id,
        'campaign_id' => $otherCampaign->id,
        'campaign_title' => $otherCampaign->title,
        'status' => 'completed',
        'created_by' => $this->user->id,
    ]);

    $this->getJson("/api/v1/org/donors?filter%5BcampaignId%5D={$campaign->id}&filter%5Bstatus%5D=contacting")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.campaignId', $campaign->id)
        ->assertJsonPath('data.0.status', 'contacting')
        ->assertJsonPath('data.0.targetType', 'campaign');
});

test('donor phone must be a Syrian mobile number', function () {
    $this->postJson('/api/v1/org/donors', [
        'name' => 'Invalid Donor',
        'email' => 'invalid@example.com',
        'phone' => '1234567890',
        'city' => 'دمشق',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});
test('applicant filtering and crud', function () {
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
        'phone' => '0998765432',
        'campaignTitle' => 'Volunteer Program',
        'applicantStatus' => 'under_review',
        'appliedAt' => now()->toIso8601String(),
    ];

    $created = $this->postJson('/api/v1/org/applicants', $payload)
        ->assertCreated()
        ->json('data.id');

    $this->patchJson("/api/v1/org/applicants/{$created}", [
        ...$payload,
        'applicantStatus' => 'accepted',
    ])->assertOk()->assertJsonPath('data.applicantStatus', 'accepted');

    $this->deleteJson("/api/v1/org/applicants/{$created}")
        ->assertOk()
        ->assertJsonPath('message', 'Data deleted successfully.');
});

test('applicant list filters campaign applications versus standalone volunteer post applications', function () {
    $campaign = Campaign::factory()->create(['organization_id' => $this->organization->id]);
    $post = Post::factory()->published()->create([
        'organization_id' => $this->organization->id,
        'campaign_id' => null,
        'type' => 'volunteer_opportunity',
        'title' => 'Standalone volunteer opportunity',
    ]);

    CampaignApplication::query()->create([
        'organization_id' => $this->organization->id,
        'campaign_id' => $campaign->id,
        'name' => 'Campaign Applicant',
        'email' => 'campaign-applicant@example.com',
        'campaign_title' => $campaign->title,
        'applicant_status' => 'pending',
        'applied_at' => now(),
        'source' => 'mobile_app',
        'campaign_ref' => $campaign->id,
        'request_type' => 'volunteer',
        'created_by' => $this->user->id,
    ]);
    CampaignApplication::query()->create([
        'organization_id' => $this->organization->id,
        'campaign_id' => null,
        'name' => 'Post Applicant',
        'email' => 'post-applicant@example.com',
        'campaign_title' => $post->title,
        'applicant_status' => 'pending',
        'applied_at' => now(),
        'source' => 'mobile_app',
        'campaign_ref' => $post->id,
        'request_type' => 'volunteer',
        'created_by' => $this->user->id,
    ]);

    $this->getJson('/api/v1/org/applicants?filter%5BtargetType%5D=campaign')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.targetType', 'campaign')
        ->assertJsonPath('data.0.campaignId', $campaign->id);

    $this->getJson('/api/v1/org/applicants?filter%5BtargetType%5D=post')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.targetType', 'post')
        ->assertJsonPath('data.0.postId', $post->id);
});

test('organization accepts contacts and completes a volunteer application', function () {
    $applicantUser = User::factory()->create();
    $campaign = Campaign::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'active',
    ]);
    $application = CampaignApplication::query()->create([
        'organization_id' => $this->organization->id,
        'campaign_id' => $campaign->id,
        'name' => $applicantUser->name,
        'email' => $applicantUser->email,
        'campaign_title' => $campaign->title,
        'applicant_status' => 'pending',
        'applied_at' => now(),
        'source' => 'mobile_app',
        'campaign_ref' => $campaign->id,
        'request_type' => 'volunteer',
        'created_by' => $applicantUser->id,
    ]);

    $this->patchJson("/api/v1/org/applicants/{$application->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.applicantStatus', 'accepted')
        ->assertJsonPath('data.can.contact', true);

    $this->patchJson("/api/v1/org/applicants/{$application->id}/contact")
        ->assertOk()
        ->assertJsonPath('data.applicantStatus', 'contacting')
        ->assertJsonPath('data.can.complete', true);

    $this->patchJson("/api/v1/org/applicants/{$application->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.applicantStatus', 'completed');

    $this->assertDatabaseHas('notifications', [
        'recipient_id' => $applicantUser->id,
        'event_type' => 'application.completed',
        'category' => 'applicant',
    ]);
});

test('applicant phone must be a Syrian mobile number', function () {
    $this->postJson('/api/v1/org/applicants', [
        'name' => 'Invalid Applicant',
        'phone' => '098765432',
        'campaignTitle' => 'Volunteer Program',
        'applicantStatus' => 'pending',
        'appliedAt' => now()->toIso8601String(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});
test('org notifications read state flow', function () {
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
});
