<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Category;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->grantPermissions($this->user, [
        [PermissionGroup::CATEGORY, PermissionAction::VIEW],
        [PermissionGroup::CATEGORY, PermissionAction::CREATE],
        [PermissionGroup::CATEGORY, PermissionAction::UPDATE],
        [PermissionGroup::CATEGORY, PermissionAction::DELETE],
    ]);
    Sanctum::actingAs($this->user);
});
test('lists categories', function () {
    Category::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/admin/categories');

    $response->assertOk();
    $response->assertJsonPath('message', 'Data retrieved successfully.');
    expect($response->json('data'))->toHaveCount(3);
});
test('creates a category', function () {
    $payload = [
        'name' => 'Health',
        'target' => 'campaign',
        'description' => 'Campaign categories for health work',
        'status' => 'active',
    ];

    $response = $this->postJson('/api/v1/admin/categories', $payload);

    $response->assertCreated();
    expect($response->json('data.name'))->toEqual('Health');
    $this->assertDatabaseHas('categories', ['name' => 'Health']);
});
test('shows a single category', function () {
    $category = Category::factory()->create();

    $response = $this->getJson("/api/v1/admin/categories/{$category->id}");

    $response->assertOk();
    expect($response->json('data.id'))->toEqual($category->id);
});
test('updates a category', function () {
    $category = Category::factory()->create();

    $payload = [
        'name' => 'Updated category',
        'target' => 'post',
        'description' => 'Updated description',
        'status' => 'inactive',
    ];

    $response = $this->patchJson("/api/v1/admin/categories/{$category->id}", $payload);

    $response->assertOk();
    expect($response->json('data.name'))->toEqual('Updated category');
    expect($response->json('data.status'))->toEqual('inactive');
});
test('updates category status', function () {
    $category = Category::factory()->create(['status' => 'active']);

    $response = $this->patchJson("/api/v1/admin/categories/{$category->id}/status", [
        'status' => 'inactive',
    ]);

    $response->assertOk();
    expect($response->json('data.status'))->toEqual('inactive');
});
test('deletes a category', function () {
    $category = Category::factory()->create();

    $response = $this->deleteJson("/api/v1/admin/categories/{$category->id}");

    $response->assertOk()->assertJsonPath('message', 'Data deleted successfully.');
    $this->assertSoftDeleted('categories', ['id' => $category->id]);
});
