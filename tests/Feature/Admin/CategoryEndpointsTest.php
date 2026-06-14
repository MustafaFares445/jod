<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->grantPermissions($this->user, [
            [PermissionGroup::CATEGORY, PermissionAction::VIEW],
            [PermissionGroup::CATEGORY, PermissionAction::CREATE],
            [PermissionGroup::CATEGORY, PermissionAction::UPDATE],
            [PermissionGroup::CATEGORY, PermissionAction::DELETE],
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_lists_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/admin/categories');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_creates_a_category(): void
    {
        $payload = [
            'name' => 'Health',
            'target' => 'campaign',
            'description' => 'Campaign categories for health work',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/v1/admin/categories', $payload);

        $response->assertCreated();
        $this->assertEquals('Health', $response->json('data.name'));
        $this->assertDatabaseHas('categories', ['name' => 'Health']);
    }

    public function test_shows_a_single_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->getJson("/api/v1/admin/categories/{$category->id}");

        $response->assertOk();
        $this->assertEquals($category->id, $response->json('data.id'));
    }

    public function test_updates_a_category(): void
    {
        $category = Category::factory()->create();

        $payload = [
            'name' => 'Updated category',
            'target' => 'post',
            'description' => 'Updated description',
            'status' => 'inactive',
        ];

        $response = $this->patchJson("/api/v1/admin/categories/{$category->id}", $payload);

        $response->assertOk();
        $this->assertEquals('Updated category', $response->json('data.name'));
        $this->assertEquals('inactive', $response->json('data.status'));
    }

    public function test_updates_category_status(): void
    {
        $category = Category::factory()->create(['status' => 'active']);

        $response = $this->patchJson("/api/v1/admin/categories/{$category->id}/status", [
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $this->assertEquals('inactive', $response->json('data.status'));
    }

    public function test_deletes_a_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/v1/admin/categories/{$category->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }
}
