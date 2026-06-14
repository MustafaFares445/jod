<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticleEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->grantPermissions($this->user, [
            [PermissionGroup::ARTICLE, PermissionAction::VIEW],
            [PermissionGroup::ARTICLE, PermissionAction::CREATE],
            [PermissionGroup::ARTICLE, PermissionAction::UPDATE],
            [PermissionGroup::ARTICLE, PermissionAction::DELETE],
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_lists_articles(): void
    {
        Article::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/admin/articles');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_creates_an_article(): void
    {
        $payload = [
            'title' => 'Getting Started with Our Platform',
            'excerpt' => 'Learn the basics of using our platform',
            'content' => 'This is the full article content...',
            'status' => 'published',
            'authorName' => 'John Doe',
        ];

        $response = $this->postJson('/api/v1/admin/articles', $payload);

        $response->assertCreated();
        $this->assertEquals('Getting Started with Our Platform', $response->json('data.title'));
        $this->assertEquals('published', $response->json('data.status'));
        $this->assertNotEmpty($response->json('data.slug'));
    }

    public function test_auto_generates_slug_for_article(): void
    {
        $payload = [
            'title' => 'My Test Article',
            'excerpt' => 'Test excerpt',
            'content' => 'Test content',
            'status' => 'draft',
            'authorName' => 'Jane Doe',
        ];

        $response = $this->postJson('/api/v1/admin/articles', $payload);

        $response->assertCreated();
        $this->assertEquals('my-test-article', $response->json('data.slug'));
    }

    public function test_sets_published_at_when_publishing(): void
    {
        $payload = [
            'title' => 'Published Article',
            'excerpt' => 'Test excerpt',
            'status' => 'published',
            'authorName' => 'Test Author',
        ];

        $response = $this->postJson('/api/v1/admin/articles', $payload);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('data.publishedAt'));
    }

    public function test_shows_a_single_article(): void
    {
        $article = Article::factory()->create();

        $response = $this->getJson("/api/v1/admin/articles/{$article->id}");

        $response->assertOk();
        $this->assertEquals($article->id, $response->json('data.id'));
        $this->assertEquals($article->title, $response->json('data.title'));
    }

    public function test_updates_an_article(): void
    {
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
        $this->assertEquals('Updated Title', $response->json('data.title'));
        $this->assertEquals('Updated Author', $response->json('data.authorName'));
    }

    public function test_deletes_an_article(): void
    {
        $article = Article::factory()->create();

        $response = $this->deleteJson("/api/v1/admin/articles/{$article->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }

    public function test_filters_articles_by_status(): void
    {
        Article::factory()->published()->create(['title' => 'Published']);
        Article::factory()->draft()->create(['title' => 'Draft']);

        $response = $this->getJson('/api/v1/admin/articles?filter.status=published');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('published', $response->json('data.0.status'));
    }

    public function test_searches_articles_by_title(): void
    {
        Article::factory()->create(['title' => 'Beginners Guide']);
        Article::factory()->create(['title' => 'Advanced Topics']);

        $response = $this->getJson('/api/v1/admin/articles?filter.search=Beginners');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Beginners Guide', $response->json('data.0.title'));
    }
}
