<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Article;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
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

    $response = $this->getJson('/api/v1/admin/articles');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});
test('creates an article', function () {
    $payload = [
        'title' => 'Getting Started with Our Platform',
        'excerpt' => 'Learn the basics of using our platform',
        'content' => 'This is the full article content...',
        'status' => 'published',
        'authorName' => 'John Doe',
    ];

    $response = $this->postJson('/api/v1/admin/articles', $payload);

    $response->assertCreated();
    expect($response->json('data.title'))->toEqual('Getting Started with Our Platform');
    expect($response->json('data.status'))->toEqual('published');
    expect($response->json('data.slug'))->not->toBeEmpty();
});
test('auto generates slug for article', function () {
    $payload = [
        'title' => 'My Test Article',
        'excerpt' => 'Test excerpt',
        'content' => 'Test content',
        'status' => 'draft',
        'authorName' => 'Jane Doe',
    ];

    $response = $this->postJson('/api/v1/admin/articles', $payload);

    $response->assertCreated();
    expect($response->json('data.slug'))->toEqual('my-test-article');
});
test('sets published at when publishing', function () {
    $payload = [
        'title' => 'Published Article',
        'excerpt' => 'Test excerpt',
        'status' => 'published',
        'authorName' => 'Test Author',
    ];

    $response = $this->postJson('/api/v1/admin/articles', $payload);

    $response->assertCreated();
    expect($response->json('data.publishedAt'))->not->toBeEmpty();
});
test('shows a single article', function () {
    $article = Article::factory()->create();

    $response = $this->getJson("/api/v1/admin/articles/{$article->id}");

    $response->assertOk();
    expect($response->json('data.id'))->toEqual($article->id);
    expect($response->json('data.title'))->toEqual($article->title);
});
test('updates an article', function () {
    $article = Article::factory()->create();

    $payload = [
        'title' => 'Updated Title',
        'excerpt' => 'Updated excerpt',
        'content' => 'Updated content',
        'status' => 'published',
        'authorName' => 'Updated Author',
    ];

    $response = $this->patchJson("/api/v1/admin/articles/{$article->id}", $payload);

    $response->assertOk();
    expect($response->json('data.title'))->toEqual('Updated Title');
    expect($response->json('data.authorName'))->toEqual('Updated Author');
});
test('deletes an article', function () {
    $article = Article::factory()->create();

    $response = $this->deleteJson("/api/v1/admin/articles/{$article->id}");

    $response->assertOk()->assertJsonPath('message', 'Data deleted successfully.');
    $this->assertDatabaseMissing('articles', ['id' => $article->id]);
});
test('filters articles by status', function () {
    Article::factory()->published()->create(['title' => 'Published']);
    Article::factory()->draft()->create(['title' => 'Draft']);

    $response = $this->getJson('/api/v1/admin/articles?filter.status=published');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toEqual('published');
});
test('searches articles by title', function () {
    Article::factory()->create(['title' => 'Beginners Guide']);
    Article::factory()->create(['title' => 'Advanced Topics']);

    $response = $this->getJson('/api/v1/admin/articles?filter.search=Beginners');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.title'))->toEqual('Beginners Guide');
});
