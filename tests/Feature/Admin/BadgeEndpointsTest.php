<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Badge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BadgeEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->grantPermissions($this->user, [
            'badges.view',
            'badges.create',
            'badges.update',
            'badges.delete',
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_lists_badges(): void
    {
        Badge::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/admin/badges');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_creates_a_badge(): void
    {
        $payload = [
            'name' => 'Test Badge',
            'description' => 'This is a test badge',
            'criteria' => 'Complete 10 posts',
            'iconName' => 'star',
            'isActive' => true,
        ];

        $response = $this->postJson('/api/v1/admin/badges', $payload);

        $response->assertCreated();
        $this->assertEquals('Test Badge', $response->json('data.name'));
        $this->assertTrue($response->json('data.isActive'));
        $this->assertDatabaseHas('badges', ['name' => 'Test Badge']);
    }

    public function test_shows_a_single_badge(): void
    {
        $badge = Badge::factory()->create();

        $response = $this->getJson("/api/v1/admin/badges/{$badge->id}");

        $response->assertOk();
        $this->assertEquals($badge->id, $response->json('data.id'));
        $this->assertEquals($badge->name, $response->json('data.name'));
    }

    public function test_updates_a_badge(): void
    {
        $badge = Badge::factory()->create();

        $payload = [
            'name' => 'Updated Badge',
            'description' => 'Updated description',
            'criteria' => 'Updated criteria',
            'iconName' => 'heart',
            'isActive' => false,
        ];

        $response = $this->patchJson("/api/v1/admin/badges/{$badge->id}", $payload);

        $response->assertOk();
        $this->assertEquals('Updated Badge', $response->json('data.name'));
        $this->assertFalse($response->json('data.isActive'));
    }

    public function test_updates_badge_status(): void
    {
        $badge = Badge::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/api/v1/admin/badges/{$badge->id}/status", ['isActive' => false]);

        $response->assertOk();
        $this->assertFalse($response->json('data.isActive'));
    }

    public function test_deletes_a_badge(): void
    {
        $badge = Badge::factory()->create();

        $response = $this->deleteJson("/api/v1/admin/badges/{$badge->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('badges', ['id' => $badge->id]);
    }

    public function test_filters_badges_by_search(): void
    {
        Badge::factory()->create(['name' => 'Popular Badge']);
        Badge::factory()->create(['name' => 'Rare Badge']);

        $response = $this->getJson('/api/v1/admin/badges?filter.search=Popular');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Popular Badge', $response->json('data.0.name'));
    }

    public function test_filters_badges_by_active_status(): void
    {
        Badge::factory()->create(['is_active' => true]);
        Badge::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/admin/badges?filter.isActive=true');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.isActive'));
    }
}
