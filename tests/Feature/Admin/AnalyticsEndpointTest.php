<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Database\Seeders\Permissions\PermissionsSeeder;
use Database\Seeders\UserSeeder;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->grantPermissions($this->user, [
        [PermissionGroup::USER, PermissionAction::VIEW],
    ]);
    Sanctum::actingAs($this->user);
});
test('returns admin overview with stats', function () {
    User::factory()->count(5)->create();
    Organization::factory()->count(3)->create();
    $organizationId = Organization::query()->value('id');

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
    expect($response->json('data.stats'))->toBeArray();
    expect($response->json('data.stats'))->not->toBeEmpty();
    expect($response->json('data.activity'))->toBeArray();
});
test('seeded admin user receives all permissions', function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(UserSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@jod.com')
        ->firstOrFail();

    expect($admin->getAllPermissions()->pluck('name')->all())->toEqualCanonicalizing(PermissionCatalog::names());
});
test('seeded admin user can access admin overview', function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(UserSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@jod.com')
        ->firstOrFail();

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/overview');

    $response->assertOk();
    expect($response->json('data.stats'))->toBeArray();
    expect($response->json('data.activity'))->toBeArray();
});
test('forbids admin overview without users view permission', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/admin/overview')
        ->assertForbidden();
});
test('returns kpi data for 7 days', function () {
    User::factory()->create();
    Post::query()->create(['title' => 'Analytics post']);

    $response = $this->getJson('/api/v1/admin/analytics/kpis?range=7d');

    $response->assertOk();
    expect($response->json('data.kpis'))->toBeArray();
    expect($response->json('data.kpis'))->not->toBeEmpty();
});
test('returns kpi data for different ranges', function () {
    foreach (['7d', '30d', '90d', '12m'] as $range) {
        $response = $this->getJson("/api/v1/admin/analytics/kpis?range={$range}");
        $response->assertOk();
        expect($response->json('data.kpis'))->toBeArray();
    }
});
test('kpi percentage change stays integer when previous period has records', function () {
    User::factory()->create([
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ]);

    $response = $this->getJson('/api/v1/admin/analytics/kpis?range=30d');

    $response->assertOk();
    expect($response->json('data.kpis.0.changeVsLastMonth'))->toBeInt();
});
test('returns weekly stats', function () {
    User::factory()->count(5)->create();
    Post::query()->create(['title' => 'Weekly post 1']);
    Post::query()->create(['title' => 'Weekly post 2']);
    Post::query()->create(['title' => 'Weekly post 3']);

    $response = $this->getJson('/api/v1/admin/analytics/weekly?range=7d');

    $response->assertOk();
    expect($response->json('data.rows'))->toBeArray();
});
test('validates range parameter for kpis', function () {
    $response = $this->getJson('/api/v1/admin/analytics/kpis?range=invalid');

    $response->assertUnprocessable();
});
test('validates range parameter for weekly stats', function () {
    $response = $this->getJson('/api/v1/admin/analytics/weekly?range=invalid');

    $response->assertUnprocessable();
});
