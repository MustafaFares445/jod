<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Post;
use App\Models\SavedPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifySavedPostUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $postId,
        public readonly string $distributionBatchId,
    ) {}

    public function handle(): void
    {
        $post = Post::query()
            ->whereKey($this->postId)
            ->where('status', 'published')
            ->first();

        if ($post === null) {
            return;
        }

        SavedPost::query()
            ->where('post_id', $post->id)
            ->when(
                filled($post->author_id),
                fn ($query) => $query->where('user_id', '!=', $post->author_id),
            )
            ->select(['id', 'user_id'])
            ->chunkById(200, function ($savedPosts) use ($post): void {
                foreach ($savedPosts as $savedPost) {
                    Notification::query()->firstOrCreate(
                        [
                            'distribution_batch_id' => $this->distributionBatchId,
                            'recipient_id' => $savedPost->user_id,
                        ],
                        [
                            'title' => 'تحديث على منشور محفوظ',
                            'body' => 'تم تحديث المنشور المحفوظ'.(filled($post->title) ? ': '.$post->title : '.'),
                            'mailbox' => 'inbox',
                            'status' => 'unread',
                            'category' => 'post',
                            'recipient_scope' => 'users',
                            'priority' => 'normal',
                            'reference_label' => 'فتح المنشور',
                            'reference_path' => '/posts/'.$post->id.'?saved=updated',
                            'organization_id' => $post->organization_id,
                            'sent_at' => now(),
                        ],
                    );
                }
            });
    }
}
