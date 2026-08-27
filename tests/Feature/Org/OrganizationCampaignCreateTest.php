<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('organization owner can create a student campaign with a long summary', function () {
    [$owner, $organization] = organization_campaign_create_test_owner();
    $category = Category::factory()->create(['status' => 'active']);
    $summary = str_repeat('دعم الطلاب الجامعيين بالمستلزمات الدراسية ومساندتهم للاستمرار في التعليم. ', 12);

    expect(mb_strlen($summary))->toBeGreaterThan(255);

    $response = $this->actingAs($owner)->postJson('/api/v1/org/campaigns', [
        'title' => 'دعم الطلاب الجامعيين بالمستلزمات الدراسية',
        'summary' => $summary,
        'categoryId' => $category->id,
        'audience' => 'student',
        'status' => 'active',
        'location' => 'Damascus',
        'goalAmount' => 5000000,
        'beneficiariesCount' => 200,
        'startDate' => '2026-09-01T09:00:00',
        'endDate' => '2026-10-10T09:00:00',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.audience', 'student')
        ->assertJsonPath('data.summary', $summary);

    $this->assertDatabaseHas('campaigns', [
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'audience' => 'student',
        'status' => 'active',
        'summary' => $summary,
    ]);
});

/** @return array{User, Organization} */
function organization_campaign_create_test_owner(): array
{
    $organization = Organization::factory()->create([
        'status' => 'active',
        'verification_status' => 'verified',
    ]);
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
