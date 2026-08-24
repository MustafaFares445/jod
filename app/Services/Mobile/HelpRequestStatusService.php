<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\HelpOfferStatus;
use App\Enums\HelpRequestStatus;
use App\Models\Post;

class HelpRequestStatusService
{
    /**
     * Recalculate a help request's operational state without overriding an explicit fulfilled state.
     */
    public function sync(Post $post): Post
    {
        if ($post->type !== 'help_request') {
            return $post;
        }

        if ($post->help_status === HelpRequestStatus::Fulfilled) {
            return $post;
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
        $post->forceFill(['help_status' => HelpRequestStatus::Fulfilled])->save();

        return $post->refresh();
    }

    public function reopen(Post $post): Post
    {
        $post->forceFill(['help_status' => HelpRequestStatus::Open])->save();

        return $this->sync($post->refresh());
    }
}
