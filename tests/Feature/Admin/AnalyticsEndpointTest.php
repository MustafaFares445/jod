<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->grantPermissions($this->user, ['users.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_returns_admin_overview_with_stats(): void
    {
        User::factory()->count(5)->create();
        Organization::factory()->count(3)->create();
        $organizationId = (int) Organization::query()->value('id');

        Post::query()->create(['title' => 'Pending post 1', 'status' => 'pending']);
        Post::query()->create(['title' => 'Pending post 2', 'status' => 'pending']);
        Campaign::query()->create([
            'title' => 'Pending campaign',
            'status' => 'pending',
            'organization_id' => $organizationId,
        ]);
        Report::query()->create([
            'title' => 'New report',
            'description' => 'Report description',
            'status' => 'new',
        ]);

        $response = $this->getJson('/api/v1/admin/overview');

        $response->assertOk();
        $this->assertIsArray($response->json('data.stats'));
        $this->assertNotEmpty($response->json('data.stats'));
        $this->assertIsArray($response->json('data.activity'));
    }

    public function test_returns_kpi_data_for_7_days(): void
    {
        User::factory()->create();
        Post::query()->create(['title' => 'Analytics post']);

        $response = $this->getJson('/api/v1/admin/analytics/kpis?range=7d');

        $response->assertOk();
        $this->assertIsArray($response->json('data.kpis'));
        $this->assertNotEmpty($response->json('data.kpis'));
    }

    public function test_returns_kpi_data_for_different_ranges(): void
    {
        foreach (['7d', '30d', '90d', '12m'] as $range) {
            $response = $this->getJson("/api/v1/admin/analytics/kpis?range={$range}");
            $response->assertOk();
            $this->assertIsArray($response->json('data.kpis'));
        }
    }

    public function test_returns_weekly_stats(): void
    {
        User::factory()->count(5)->create();
        Post::query()->create(['title' => 'Weekly post 1']);
        Post::query()->create(['title' => 'Weekly post 2']);
        Post::query()->create(['title' => 'Weekly post 3']);

        $response = $this->getJson('/api/v1/admin/analytics/weekly?range=7d');

        $response->assertOk();
        $this->assertIsArray($response->json('data.rows'));
    }

    public function test_validates_range_parameter_for_kpis(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/kpis?range=invalid');

        $response->assertUnprocessable();
    }

    public function test_validates_range_parameter_for_weekly_stats(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/weekly?range=invalid');

        $response->assertUnprocessable();
    }
}
