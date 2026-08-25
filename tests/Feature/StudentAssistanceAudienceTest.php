<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('posts and campaigns default to general audience', function () {
    $organization = Organization::factory()->create();

    $post = Post::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'General post',
        'type' => 'general',
        'status' => 'published',
        'organization_id' => $organization->id,
        'published_at' => now(),
    ]);

    $campaign = Campaign::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'General campaign',
        'status' => 'active',
        'organization_id' => $organization->id,
    ]);

    expect($post->refresh()->audience)->toBe('general')
        ->and($campaign->refresh()->audience)->toBe('general');
});

test('mobile post discovery filters student audience and exposes it', function () {
    $organization = Organization::factory()->create();

    $student = Post::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Student laptop request',
        'content' => 'Laptop needed for study',
        'type' => 'help_request',
        'audience' => 'student',
        'status' => 'published',
        'location' => 'Aleppo',
        'organization_id' => $organization->id,
        'published_at' => now(),
    ]);

    Post::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'General help request',
        'type' => 'help_request',
        'audience' => 'general',
        'status' => 'published',
        'location' => 'Aleppo',
        'organization_id' => $organization->id,
        'published_at' => now(),
    ]);

    $this->getJson('/api/mobile/discovery/posts?audience=student&type=help_request&location=Aleppo')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $student->id)
        ->assertJsonPath('data.0.audience', 'student');
});

test('mobile campaign discovery filters student audience and exposes it', function () {
    $organization = Organization::factory()->create();

    $student = Campaign::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'University support',
        'audience' => 'student',
        'status' => 'active',
        'location' => 'Aleppo',
        'organization_id' => $organization->id,
    ]);

    Campaign::query()->create([
        'id' => (string) Str::uuid(),
        'title' => 'General support',
        'audience' => 'general',
        'status' => 'active',
        'location' => 'Aleppo',
        'organization_id' => $organization->id,
    ]);

    $this->getJson('/api/mobile/discovery/campaigns?audience=student&location=Aleppo')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $student->id)
        ->assertJsonPath('data.0.audience', 'student');
});

test('discovery rejects unsupported audiences', function () {
    $this->getJson('/api/mobile/discovery/posts?audience=vip')->assertUnprocessable()->assertJsonValidationErrors('audience');
    $this->getJson('/api/mobile/discovery/campaigns?audience=vip')->assertUnprocessable()->assertJsonValidationErrors('audience');
});
