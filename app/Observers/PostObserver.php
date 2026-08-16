<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\NotifySavedPostUpdate;
use App\Models\Post;
use Illuminate\Support\Str;

class PostObserver
{
    /** @var list<string> */
    private const SAVED_NOTIFICATION_FIELDS = [
        'title',
        'summary',
        'content',
        'type',
        'location',
        'campaign_id',
    ];

    public function updated(Post $post): void
    {
        if ($post->status !== 'published' || ! $post->wasChanged(self::SAVED_NOTIFICATION_FIELDS)) {
            return;
        }

        NotifySavedPostUpdate::dispatch(
            (string) $post->id,
            (string) Str::uuid(),
        )->afterCommit();
    }
}
