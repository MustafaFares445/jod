<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\HelpOfferStatus;
use App\Enums\HelpRequestStatus;
use App\Models\Post;

class HelpRequestStatusService
{
    /**
     * Recalculate a help request's operational state without overriding an explicit terminal state.
     */
    public function sync(Post $post): Post
    {
        if ($post->type !== 'help_request') {
            return $post;
        }

        if ($post->help_status?->isTerminal()) {
            return $post;
        }

        if ($post->expires_at !== null && $post->expires_at->isPast()) {
            $post->forceFill([
                'help_status' => HelpRequestStatus::Expired,
            ])->save();

            return $post->refresh();
        }

        $hasProgressingOffer = $post->helpOffers()
            ->whereIn('status', [
                HelpOfferStatus::Accepted->value,
                HelpOfferStatus::Contacting->value,
                HelpOfferStatus::Agreed->value,
            ])
            ->exists();

        $post->forceFill([
            'help_status' => $hasProgressingOffer
                ? HelpRequestStatus::InProgress
                : HelpRequestStatus::Open,
        ])->save();

        return $post->refresh();
    }

    public function fulfill(Post $post): Post
    {
        $post->forceFill([
            'help_status' => HelpRequestStatus::Fulfilled,
            'fulfilled_at' => $post->fulfilled_at ?? now(),
        ])->save();

        return $post->refresh();
    }

    public function reopen(Post $post): Post
    {
        $post->forceFill([
            'help_status' => HelpRequestStatus::Open,
            'fulfilled_at' => null,
        ])->save();

        return $this->sync($post->refresh());
    }
}
