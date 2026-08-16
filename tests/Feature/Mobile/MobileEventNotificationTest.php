<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Jobs\NotifySavedPostUpdate;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Post;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileEventNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_application_status_change_notifies_applicant_and_syncs_count(): void
    {
        $organization = Organization::factory()->create();
        $applicant = User::factory()->create();
        $campaign = Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Volunteer Campaign',
            'category' => 'employment',
            'status' => 'active',
            'organization_id' => $organization->id,
            'applicants_count' => 1,
        ]);
        $post = Post::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Volunteer with us',
            'type' => 'volunteer_opportunity',
            'status' => 'published',
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'published_at' => now(),
        ]);
        $application = CampaignApplication::query()->create([
            'organization_id' => $organization->id,
            'campaign_id' => $campaign->id,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'phone' => $applicant->phone,
            'campaign_title' => $campaign->title,
            'applicant_status' => 'pending',
            'applied_at' => now(),
            'source' => 'mobile_app',
            'created_by' => $applicant->id,
        ]);

        $application->update(['applicant_status' => 'accepted']);

        $notification = Notification::query()
            ->where('recipient_id', $applicant->id)
            ->where('category', 'applicant')
            ->firstOrFail();

        $this->assertSame('قبول طلب التطوع', $notification->title);
        $this->assertSame('/posts/'.$post->id, $notification->reference_path);
        $this->assertSame(1, (int) $campaign->fresh()->applicants_count);

        Sanctum::actingAs($applicant);
        $this->getJson('/api/mobile/me/notifications?category=applicant')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'volunteer')
            ->assertJsonPath('data.0.actionLabel', 'تفاصيل النشاط');

        $application->update(['applicant_status' => 'rejected']);

        $this->assertSame(0, (int) $campaign->fresh()->applicants_count);
        $this->assertSame(
            2,
            Notification::query()
                ->where('recipient_id', $applicant->id)
                ->where('category', 'applicant')
                ->count(),
        );
    }

    public function test_user_owned_pending_and_withdrawn_transitions_do_not_notify_applicant(): void
    {
        $organization = Organization::factory()->create();
        $applicant = User::factory()->create();
        $campaign = Campaign::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Volunteer Campaign',
            'category' => 'employment',
            'status' => 'active',
            'organization_id' => $organization->id,
        ]);
        $application = CampaignApplication::query()->create([
            'organization_id' => $organization->id,
            'campaign_id' => $campaign->id,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'campaign_title' => $campaign->title,
            'applicant_status' => 'withdrawn',
            'applied_at' => now(),
            'source' => 'mobile_app',
            'created_by' => $applicant->id,
        ]);

        $application->update(['applicant_status' => 'pending']);
        $application->update(['applicant_status' => 'withdrawn']);

        $this->assertDatabaseMissing('notifications', [
            'recipient_id' => $applicant->id,
            'category' => 'applicant',
        ]);
    }

    public function test_saved_post_update_job_notifies_savers_once_per_batch(): void
    {
        $author = User::factory()->create();
        $firstSaver = User::factory()->create();
        $secondSaver = User::factory()->create();
        $post = $this->publishedPost($author, ['title' => 'Important update']);
        SavedPost::factory()->create(['post_id' => $post->id, 'user_id' => $firstSaver->id]);
        SavedPost::factory()->create(['post_id' => $post->id, 'user_id' => $secondSaver->id]);
        SavedPost::factory()->create(['post_id' => $post->id, 'user_id' => $author->id]);
        $batchId = (string) Str::uuid();
        $job = new NotifySavedPostUpdate($post->id, $batchId);

        $job->handle();
        $job->handle();

        $notifications = Notification::query()
            ->where('distribution_batch_id', $batchId)
            ->orderBy('recipient_id')
            ->get();

        $this->assertCount(2, $notifications);
        $this->assertEqualsCanonicalizing(
            [$firstSaver->id, $secondSaver->id],
            $notifications->pluck('recipient_id')->all(),
        );
        $this->assertFalse($notifications->contains('recipient_id', $author->id));
        $this->assertTrue($notifications->every(fn (Notification $notification) => $notification->category === 'post'));
        $this->assertTrue($notifications->every(fn (Notification $notification) => str_contains((string) $notification->reference_path, 'saved=updated')));

        Sanctum::actingAs($firstSaver);
        $this->getJson('/api/mobile/me/notifications?category=post')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'saved')
            ->assertJsonPath('data.0.actionLabel', 'فتح المنشور');
    }

    public function test_post_content_update_queues_saved_notification_but_counter_updates_do_not(): void
    {
        $author = User::factory()->create();
        $post = $this->publishedPost($author);

        Queue::fake();
        $post->update(['title' => 'Changed title']);

        Queue::assertPushed(NotifySavedPostUpdate::class, fn (NotifySavedPostUpdate $job) => $job->postId === $post->id);

        Queue::fake();
        $post->update([
            'reactions_count' => 10,
            'comments_count' => 4,
            'shares_count' => 3,
        ]);

        Queue::assertNotPushed(NotifySavedPostUpdate::class);
    }

    public function test_saved_update_job_skips_post_if_it_is_no_longer_published(): void
    {
        $author = User::factory()->create();
        $saver = User::factory()->create();
        $post = $this->publishedPost($author);
        SavedPost::factory()->create(['post_id' => $post->id, 'user_id' => $saver->id]);
        $post->update(['status' => 'archived']);

        (new NotifySavedPostUpdate($post->id, (string) Str::uuid()))->handle();

        $this->assertDatabaseMissing('notifications', [
            'recipient_id' => $saver->id,
            'category' => 'post',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedPost(User $author, array $overrides = []): Post
    {
        return Post::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => 'Published post',
            'content' => 'Published content',
            'type' => 'help_request',
            'status' => 'published',
            'author_id' => $author->id,
            'published_at' => now(),
        ], $overrides));
    }
}
