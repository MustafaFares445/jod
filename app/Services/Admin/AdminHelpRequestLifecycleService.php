<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\HelpRequestStatus;
use App\Enums\PostUrgency;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AdminHelpRequestLifecycleService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function updateUrgency(User $actor, Post $post, PostUrgency $urgency, ?string $reason): Post
    {
        $this->assertHelpRequest($post);
        $old = $post->only(['urgency', 'urgency_reason', 'urgency_reviewed_by', 'urgency_reviewed_at']);
        $post->forceFill(['urgency' => $urgency, 'urgency_reason' => filled($reason) ? trim((string) $reason) : null, 'urgency_reviewed_by' => $actor->id, 'urgency_reviewed_at' => now()])->save();
        $this->audit->record($actor, 'help_request.urgency_changed', 'post', (string) $post->id, $old, $post->only(['urgency', 'urgency_reason', 'urgency_reviewed_by', 'urgency_reviewed_at']));
        return $post->refresh();
    }

    public function updateExpiration(User $actor, Post $post, ?string $expiresAt): Post
    {
        $this->assertHelpRequest($post);
        $old = ['expires_at' => $post->expires_at?->toIso8601String()];
        $post->forceFill(['expires_at' => $expiresAt ? Carbon::parse($expiresAt) : null])->save();
        $this->audit->record($actor, 'help_request.expiration_changed', 'post', (string) $post->id, $old, ['expires_at' => $post->expires_at?->toIso8601String()]);
        return $post->refresh();
    }

    public function updateFulfillment(User $actor, Post $post, HelpRequestStatus $status): Post
    {
        $this->assertHelpRequest($post);
        if (in_array($status, [HelpRequestStatus::Open, HelpRequestStatus::InProgress], true) && $post->expires_at !== null && $post->expires_at->isPast()) {
            throw ValidationException::withMessages(['status' => ['Extend or clear the expiration before reactivating an expired help request.']]);
        }
        $old = ['help_status' => $post->help_status?->value, 'fulfilled_at' => $post->fulfilled_at?->toIso8601String()];
        $updates = ['help_status' => $status];
        if (in_array($status, [HelpRequestStatus::Fulfilled, HelpRequestStatus::PartiallyFulfilled], true)) $updates['fulfilled_at'] = $post->fulfilled_at ?? now();
        elseif (! $status->isTerminal()) $updates['fulfilled_at'] = null;
        if ($status === HelpRequestStatus::Expired && ($post->expires_at === null || $post->expires_at->isFuture())) $updates['expires_at'] = now();
        $post->forceFill($updates)->save();
        $this->audit->record($actor, 'help_request.outcome_changed', 'post', (string) $post->id, $old, ['help_status' => $post->help_status?->value, 'fulfilled_at' => $post->fulfilled_at?->toIso8601String()]);
        return $post->refresh();
    }

    private function assertHelpRequest(Post $post): void
    {
        if ($post->type !== 'help_request') throw ValidationException::withMessages(['post' => ['Lifecycle controls are available only for help request posts.']]);
    }
}
