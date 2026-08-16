<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileBlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_blogs_return_only_currently_published_articles_with_mobile_contract(): void
    {
        $author = User::factory()->create([
            'name' => 'JOD Content',
            'email' => 'content@jod.org',
            'email_verified_at' => now(),
        ]);
        $content = implode(' ', array_fill(0, 250, 'word'));
        $published = Article::factory()->create([
            'title' => 'Volunteer guide',
            'excerpt' => 'A useful guide for volunteers.',
            'content' => $content,
            'category' => 'volunteer_guides',
            'cover_image' => 'https://example.test/cover.jpg',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'author_id' => $author->id,
            'author_name' => $author->name,
        ]);
        Article::factory()->draft()->create(['title' => 'Draft article']);
        Article::factory()->create([
            'title' => 'Future article',
            'status' => 'published',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/mobile/blogs?perPage=10');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.title', 'Volunteer guide')
            ->assertJsonPath('data.0.excerpt', 'A useful guide for volunteers.')
            ->assertJsonPath('data.0.coverImage', 'https://example.test/cover.jpg')
            ->assertJsonPath('data.0.category', 'volunteer_guides')
            ->assertJsonPath('data.0.readTimeMinutes', 2)
            ->assertJsonPath('data.0.author.id', $author->id)
            ->assertJsonPath('data.0.author.name', 'JOD Content')
            ->assertJsonPath('data.0.author.username', 'content')
            ->assertJsonPath('data.0.author.verified', true);
        $response->assertJsonMissing(['title' => 'Draft article']);
        $response->assertJsonMissing(['title' => 'Future article']);
    }

    public function test_blog_list_can_filter_by_category_and_search_content(): void
    {
        $awareness = Article::factory()->published()->create([
            'title' => 'Donation safety',
            'excerpt' => 'Verify campaigns before donating.',
            'content' => 'Transparency and verification guidance.',
            'category' => 'awareness',
        ]);
        Article::factory()->published()->create([
            'title' => 'Success story',
            'excerpt' => 'Community impact',
            'content' => 'A successful community campaign.',
            'category' => 'success_stories',
        ]);

        $this->getJson('/api/mobile/blogs?category=awareness&search=verification&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $awareness->id);
    }

    public function test_blog_detail_is_public_only_for_published_article(): void
    {
        $published = Article::factory()->published()->create();
        $draft = Article::factory()->draft()->create();

        $this->getJson("/api/mobile/blogs/{$published->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $published->id);

        $this->getJson("/api/mobile/blogs/{$draft->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_blog_categories_match_mobile_category_vocabulary(): void
    {
        $this->getJson('/api/mobile/blog-categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'awareness')
            ->assertJsonPath('data.1.id', 'success_stories')
            ->assertJsonPath('data.2.id', 'campaign_updates')
            ->assertJsonPath('data.3.id', 'volunteer_guides');
    }

    public function test_blog_filters_are_validated(): void
    {
        $this->getJson('/api/mobile/blogs?perPage=0&category=unknown&sort=random')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['perPage', 'category', 'sort'], 'error.details');
    }
}
