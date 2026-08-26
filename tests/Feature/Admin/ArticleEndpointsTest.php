<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Article;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create(['name' => 'Article Admin']);
    $this->grantPermissions($this->user, [
        [PermissionGroup::ARTICLE, PermissionAction::VIEW],
        [PermissionGroup::ARTICLE, PermissionAction::CREATE],
        [PermissionGroup::ARTICLE, PermissionAction::UPDATE],
        [PermissionGroup::ARTICLE, PermissionAction::DELETE],
    ]);
    Sanctum::actingAs($this->user);
});

test('lists articles', function () {
    Article::factory()->count(3)->create();

    $this->getJson('/api/v1/admin/articles')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('creates a published article from title and description and owns publisher on backend', function () {
    $response = $this->postJson('/api/v1/admin/articles', [
        'title' => 'Getting Started with Our Platform',
        'description' => 'This is the article description.',
        'authorName' => 'Frontend Author Must Be Ignored',
        'status' => 'draft',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Getting Started with Our Platform')
        ->assertJsonPath('data.description', 'This is the article description.')
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.authorName', 'Article Admin')
        ->assertJsonPath('data.images', [])
        ->assertJsonPath('data.videos', []);

    $article = Article::query()->findOrFail((string) $response->json('data.id'));
    expect($article->author_id)->toBe($this->user->id)
        ->and($article->published_at)->not->toBeNull();
});

test('auto generates slug and ignores frontend slug', function () {
    $response = $this->postJson('/api/v1/admin/articles', [
        'title' => 'Backend Owns This Slug',
        'description' => 'Description',
        'slug' => 'frontend-controlled-slug',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'backend-owns-this-slug');
    $this->assertDatabaseMissing('articles', ['slug' => 'frontend-controlled-slug']);
});

test('updates only title and description and keeps backend publisher', function () {
    $article = Article::factory()->create([
        'author_id' => $this->user->id,
        'author_name' => $this->user->name,
    ]);

    $response = $this->patchJson("/api/v1/admin/articles/{$article->id}", [
        'title' => 'Updated Article Title',
        'description' => 'Updated article description',
        'authorName' => 'Another Frontend Author',
        'status' => 'draft',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Updated Article Title')
        ->assertJsonPath('data.description', 'Updated article description')
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.authorName', $this->user->name)
        ->assertJsonPath('data.slug', 'updated-article-title');
});

test('article media api accepts optional multiple images and videos', function () {
    $article = Article::factory()->create([
        'author_id' => $this->user->id,
        'author_name' => $this->user->name,
    ]);

    $this->post("/api/v1/media/article/{$article->id}/images", [
        'file' => UploadedFile::fake()->image('article-one.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $this->post("/api/v1/media/article/{$article->id}/images", [
        'file' => UploadedFile::fake()->image('article-two.webp'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $this->post("/api/v1/media/article/{$article->id}/videos", [
        'file' => UploadedFile::fake()->create('article-video.mp4', 250, 'video/mp4'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $this->getJson("/api/v1/admin/articles/{$article->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.images')
        ->assertJsonCount(1, 'data.videos')
        ->assertJsonCount(3, 'data.media');
});

test('article can be created without media', function () {
    $this->postJson('/api/v1/admin/articles', [
        'title' => 'Text only article',
        'description' => 'No media is required for this article.',
    ])->assertCreated()
        ->assertJsonPath('data.images', [])
        ->assertJsonPath('data.videos', []);
});

test('deletes an article and its media', function () {
    $article = Article::factory()->create([
        'author_id' => $this->user->id,
        'author_name' => $this->user->name,
    ]);

    $upload = $this->post("/api/v1/media/article/{$article->id}/images", [
        'file' => UploadedFile::fake()->image('delete-me.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $mediaId = (string) $upload->json('data.id');

    $this->deleteJson("/api/v1/admin/articles/{$article->id}")->assertNoContent();
    $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    $this->assertDatabaseMissing('media', ['id' => $mediaId]);
});

test('filters and searches articles', function () {
    Article::factory()->published()->create(['title' => 'Beginners Guide']);
    Article::factory()->draft()->create(['title' => 'Advanced Topics']);

    $this->getJson('/api/v1/admin/articles?filter.status=published&filter.search=Beginners')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Beginners Guide');
});
