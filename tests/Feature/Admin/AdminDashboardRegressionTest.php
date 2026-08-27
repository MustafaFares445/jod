<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Post;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create([
        'name' => 'Dashboard Admin',
        'user_type' => 'admin',
        'status' => 'active',
    ]);
    $this->grantPermissions($this->admin, [
        [PermissionGroup::USER, PermissionAction::VIEW],
        [PermissionGroup::USER, PermissionAction::RESET_PASSWORD],
        [PermissionGroup::POST_REVIEW, PermissionAction::VIEW],
        [PermissionGroup::POST_REVIEW, PermissionAction::APPROVE],
    ]);
});

test('admin users nested status and role filters change the result set', function () {
    Sanctum::actingAs($this->admin);

    $matching = User::factory()->create([
        'name' => 'Active Donor',
        'email' => 'active-donor@example.test',
        'status' => 'active',
        'user_type' => 'donor',
    ]);
    User::factory()->create([
        'name' => 'Inactive Donor',
        'status' => 'inactive',
        'user_type' => 'donor',
    ]);
    User::factory()->create([
        'name' => 'Active Volunteer',
        'status' => 'active',
        'user_type' => 'volunteer',
    ]);

    $response = $this->getJson('/api/v1/admin/users?page=1&perPage=9&sort=-createdAt&filter%5Bstatus%5D=active&filter%5Brole%5D=donor');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('admin user search filters by name email or phone and stays grouped', function () {
    Sanctum::actingAs($this->admin);

    $matching = User::factory()->create([
        'name' => 'Loll Example',
        'email' => 'person@example.test',
        'phone' => '+963999111222',
        'status' => 'active',
        'user_type' => 'general',
    ]);
    User::factory()->create([
        'name' => 'Other Person',
        'email' => 'other@example.test',
        'status' => 'active',
        'user_type' => 'general',
    ]);
    User::factory()->create([
        'name' => 'Loll Inactive',
        'email' => 'inactive@example.test',
        'status' => 'inactive',
        'user_type' => 'general',
    ]);

    $response = $this->getJson('/api/v1/admin/users?page=1&perPage=9&sort=-createdAt&filter%5Bstatus%5D=active&filter%5Bsearch%5D=loll');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($matching->id);

    $this->getJson('/api/v1/admin/users?filter%5Bsearch%5D=%2B963999111222')
        ->assertOk()
        ->assertJsonPath('data.0.id', $matching->id);
});

test('admin with reset password permission can change another user password', function () {
    Sanctum::actingAs($this->admin);
    $user = User::factory()->create();

    $this->patchJson("/api/v1/admin/users/{$user->id}/password", [
        'newPassword' => '12345678',
        'newPassword_confirmation' => '12345678',
    ])->assertOk()->assertJsonPath('data.id', $user->id);

    expect(Hash::check('12345678', $user->fresh()->password))->toBeTrue();
});

test('admin can create ordinary post without media then upload images and videos through media manager', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/admin/posts', [
        'title' => 'Admin announcement',
        'description' => 'General announcement created by an administrator.',
    ])->assertOk()
        ->assertJsonPath('data.type', 'general')
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.author.id', $this->admin->id)
        ->assertJsonPath('data.images', [])
        ->assertJsonPath('data.videos', []);

    $postId = (string) $response->json('data.id');

    $first = $this->post("/api/v1/media/post/{$postId}/images", [
        'file' => UploadedFile::fake()->image('first.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.id');

    $this->post("/api/v1/media/post/{$postId}/images", [
        'file' => UploadedFile::fake()->image('second.webp'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $this->post("/api/v1/media/post/{$postId}/videos", [
        'file' => UploadedFile::fake()->create('announcement.mp4', 250, 'video/mp4'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $this->getJson("/api/v1/admin/posts/{$postId}")
        ->assertOk()
        ->assertJsonCount(2, 'data.images')
        ->assertJsonCount(1, 'data.videos')
        ->assertJsonCount(3, 'data.media')
        ->assertJsonPath('data.media.0.id', $first)
        ->assertJsonPath('data.updatedBy.id', $this->admin->id);

    expect(Post::query()->findOrFail($postId)->images()->count())->toBe(2);
});

test('weekly analytics accepts a real issued admin access token for 30d range', function () {
    $pair = app(TokenService::class)->issueTokenPair($this->admin);

    $this->withToken((string) $pair['token'])
        ->getJson('/api/v1/admin/analytics/weekly?range=30d')
        ->assertOk()
        ->assertJsonStructure(['data' => ['rows']]);
});
