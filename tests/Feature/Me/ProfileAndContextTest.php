<?php

declare(strict_types=1);

namespace Tests\Feature\Me;

use App\Models\Campaign;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAndContextTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::query()->create([
            'name' => 'Relief Org',
            'email' => 'relief@example.com',
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_returns_profile_bootstrap_data(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertOk();
        $response->assertJsonPath('data.id', $this->user->id);
        $response->assertJsonPath('data.organizationId', $this->user->organization_id);
    }

    public function test_updates_profile(): void
    {
        $response = $this->patchJson('/api/v1/me/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+962790000000',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.email', 'updated@example.com');
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'updated@example.com',
        ]);
    }

    public function test_returns_dashboard_context_counters_from_database(): void
    {
        Notification::query()->create([
            'title' => 'Unread for user',
            'body' => 'Body',
            'mailbox' => 'inbox',
            'status' => 'unread',
            'category' => 'system',
            'recipient_scope' => 'users',
            'recipient_id' => $this->user->id,
        ]);

        Notification::query()->create([
            'title' => 'Unread for org',
            'body' => 'Body',
            'mailbox' => 'inbox',
            'status' => 'unread',
            'category' => 'system',
            'recipient_scope' => 'organizations',
            'organization_id' => $this->user->organization_id,
        ]);

        Notification::query()->create([
            'title' => 'Read notification',
            'body' => 'Body',
            'mailbox' => 'inbox',
            'status' => 'read',
            'category' => 'system',
            'recipient_scope' => 'organizations',
            'organization_id' => $this->user->organization_id,
        ]);

        Post::query()->create([
            'title' => 'Pending post',
            'status' => 'pending',
            'type' => 'general',
        ]);

        Campaign::query()->create([
            'title' => 'Pending campaign',
            'status' => 'pending',
            'organization_id' => $this->user->organization_id,
        ]);

        Report::query()->create([
            'title' => 'Open report',
            'description' => 'Issue',
            'status' => 'new',
            'severity' => 'medium',
            'entity_type' => 'post',
        ]);

        $response = $this->getJson('/api/v1/me/dashboard-context');

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.counters.unreadNotifications'));
        $this->assertEquals(2, $response->json('data.counters.pendingReviews'));
        $this->assertEquals(1, $response->json('data.counters.openReports'));
    }
}
