<?php

declare(strict_types=1);

use App\Models\Group;
use App\Models\Organization;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\SavedPost;
use App\Models\User;
use App\Services\MediaService;
use App\Services\Mobile\PostImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('my posts exposes the authenticated viewer like and save state on list and detail', function () {
    $user = User::factory()->create(['status' => 'active']);
    $post = Post::factory()->create([
        'author_id' => $user->id,
        'status' => 'published',
        'type' => 'help_request',
    ]);
    PostLike::query()->create(['user_id' => $user->id, 'post_id' => $post->id]);
    SavedPost::query()->create(['user_id' => $user->id, 'post_id' => $post->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/mobile/me/posts?perPage=20')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $post->id)
        ->assertJsonPath('data.0.isLiked', true)
        ->assertJsonPath('data.0.isSaved', true);

    $this->getJson("/api/mobile/me/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('data.isLiked', true)
        ->assertJsonPath('data.isSaved', true);
});

test('new mobile post and its selected images are created atomically', function () {
    Storage::fake('public');
    $user = User::factory()->create(['status' => 'active']);
    Sanctum::actingAs($user);

    $response = $this->post('/api/mobile/posts', [
        'type' => 'help_request',
        'saveAsDraft' => '1',
        'images' => [UploadedFile::fake()->image('need.jpg', 600, 600)],
    ], ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonCount(1, 'data.images')
        ->assertJsonCount(1, 'data.imageMedia');

    $postId = (string) $response->json('data.id');
    $this->assertDatabaseHas('posts', ['id' => $postId, 'author_id' => $user->id]);
    $this->assertDatabaseHas('media', ['model_type' => 'post', 'model_id' => $postId, 'prop' => 'images']);
});

test('new mobile post is rolled back when image persistence fails', function () {
    $user = User::factory()->create(['status' => 'active']);
    Sanctum::actingAs($user);
    $before = Post::query()->count();

    $this->mock(PostImageService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('add')->once()->andThrow(
            ValidationException::withMessages(['images' => ['تعذر رفع صور المنشور.']]),
        );
    });

    $this->post('/api/mobile/posts', [
        'type' => 'help_request',
        'saveAsDraft' => '1',
        'images' => [UploadedFile::fake()->image('broken.jpg')],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images'], 'error.details');

    expect(Post::query()->count())->toBe($before);
});

test('company registration requires a logo before creating any records', function () {
    $beforeOrganizations = Organization::query()->count();
    $beforeUsers = User::query()->count();

    $this->postJson('/api/v1/company/auth/register', valid_company_registration_payload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo']);

    expect(Organization::query()->count())->toBe($beforeOrganizations);
    expect(User::query()->count())->toBe($beforeUsers);
});

test('company registration creates the organization and logo in the same request', function () {
    Storage::fake('public');
    $payload = valid_company_registration_payload();
    $payload['logo'] = UploadedFile::fake()->image('organization.png', 500, 500);

    $response = $this->post('/api/v1/company/auth/register', $payload, ['Accept' => 'application/json']);

    $response->assertCreated();
    $organizationId = (string) $response->json('data.user.organizationId');
    expect($organizationId)->not->toBe('');
    $this->assertDatabaseHas('organizations', ['id' => $organizationId, 'email' => $payload['companyEmail']]);
    $this->assertDatabaseHas('media', ['model_type' => 'organization', 'model_id' => $organizationId, 'prop' => 'logo']);
});

test('company registration rolls back when logo storage fails', function () {
    $beforeOrganizations = Organization::query()->count();
    $beforeUsers = User::query()->count();

    $this->mock(MediaService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('upload')->once()->andThrow(new \RuntimeException('disk unavailable'));
    });

    $payload = valid_company_registration_payload('failed-logo@example.com', 'ORG-FAIL', 'REG-FAIL');
    $payload['logo'] = UploadedFile::fake()->image('organization.png');

    $this->post('/api/v1/company/auth/register', $payload, ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo']);

    expect(Organization::query()->count())->toBe($beforeOrganizations);
    expect(User::query()->count())->toBe($beforeUsers);
});

test('mobile search supports explicit organizations and groups result types', function () {
    $owner = User::factory()->create(['status' => 'active']);
    $organization = Organization::factory()->create([
        'name' => 'Searchable Relief Organization',
        'status' => 'active',
        'verification_status' => 'verified',
    ]);
    $group = Group::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Searchable Volunteer Group',
        'description' => 'Volunteer group used for search coverage.',
        'category' => 'community',
        'location' => 'Damascus',
        'status' => 'active',
        'purpose' => 'Community volunteering',
        'rules' => [],
        'proposed_admin_ids' => [],
    ]);

    $this->getJson('/api/mobile/search?type=organizations&search=Searchable')
        ->assertOk()
        ->assertJsonPath('data.organizations.0.id', (string) $organization->id)
        ->assertJsonPath('meta.counts.organizations', 1);

    $this->getJson('/api/mobile/search?type=groups&search=Searchable')
        ->assertOk()
        ->assertJsonPath('data.groups.0.id', (string) $group->id)
        ->assertJsonPath('meta.counts.groups', 1);
});

function valid_company_registration_payload(
    string $email = 'atomic-org@example.com',
    string $organizationNumber = 'ORG-ATOMIC',
    string $registrationNumber = 'REG-ATOMIC',
): array {
    return [
        'companyName' => 'Atomic Relief Organization',
        'ownerName' => 'Atomic Owner',
        'organizationNumber' => $organizationNumber,
        'registrationNumber' => $registrationNumber,
        'bankAccountNumber' => 'BANK-123456',
        'companyEmail' => $email,
        'companyPhone' => '+963912345678',
        'location' => 'Damascus',
        'website' => 'https://example.org',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ];
}
