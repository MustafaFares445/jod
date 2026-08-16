<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileCtaFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_donation_and_volunteer_posts_without_campaign_use_contact_cta(): void
    {
        $author = User::factory()->create([
            'phone' => '0999111222',
            'organization_id' => null,
        ]);

        foreach (['donation_campaign', 'volunteer_opportunity'] as $type) {
            $post = $this->publishedPost([
                'author_id' => $author->id,
                'organization_id' => null,
                'campaign_id' => null,
                'type' => $type,
            ]);

            $this->getJson("/api/mobile/discovery/posts/{$post->id}")
                ->assertOk()
                ->assertJsonPath('data.cta.type', 'contact')
                ->assertJsonPath('data.cta.label', 'تواصل')
                ->assertJsonPath('data.cta.targetId', $post->id)
                ->assertJsonPath('data.publisher.phoneNumber', '0999111222')
                ->assertJsonMissingPath('data.cta.state');
        }
    }

    public function test_closed_action_state_only_contains_campaign_backed_closed_ctas(): void
    {
        $author = User::factory()->create(['organization_id' => null]);
        $individual = $this->publishedPost([
            'title' => 'Individual donation post',
            'author_id' => $author->id,
            'organization_id' => null,
            'campaign_id' => null,
            'type' => 'donation_campaign',
        ]);

        $organization = Organization::factory()->create();
        $campaign = Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Closed organization campaign',
            'category' => 'health',
            'status' => 'closed',
            'organization_id' => $organization->id,
        ]);
        $campaignPost = $this->publishedPost([
            'title' => 'Closed campaign post',
            'organization_id' => $organization->id,
            'campaign_id' => $campaign->id,
            'type' => 'donation_campaign',
        ]);

        $this->getJson('/api/mobile/discovery/posts?actionState=closed&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $campaignPost->id)
            ->assertJsonPath('data.0.cta.type', 'donate')
            ->assertJsonPath('data.0.cta.state', 'closed')
            ->assertJsonMissing(['id' => $individual->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedPost(array $overrides = []): Post
    {
        return Post::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => 'Mobile post',
            'content' => 'Post details',
            'type' => 'help_request',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }
}
