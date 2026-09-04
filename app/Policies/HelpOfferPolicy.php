<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\HelpOfferStatus;
use App\Models\HelpOffer;
use App\Models\Post;
use App\Models\User;

class HelpOfferPolicy
{
    public function view(User $user, HelpOffer $offer): bool
    {
        return $this->isParticipant($user, $offer) || $user->user_type === 'admin';
    }

    public function create(User $user, Post $post): bool
    {
        return $post->type === 'help_request'
            && (string) $post->author_id !== (string) $user->id;
    }

    public function accept(User $user, HelpOffer $offer): bool
    {
        return $this->isOwner($user, $offer) && $offer->status === HelpOfferStatus::Pending;
    }

    public function reject(User $user, HelpOffer $offer): bool
    {
        return $this->isOwner($user, $offer) && $offer->status === HelpOfferStatus::Pending;
    }

    public function coordinate(User $user, HelpOffer $offer): bool
    {
        return $this->isParticipant($user, $offer);
    }

    public function confirmProvided(User $user, HelpOffer $offer): bool
    {
        return $this->isHelper($user, $offer);
    }

    public function confirmReceived(User $user, HelpOffer $offer): bool
    {
        return $this->isOwner($user, $offer);
    }

    public function markFulfilled(User $user, Post $post): bool
    {
        return (string) $post->author_id === (string) $user->id || $this->belongsToOrganization($user, $post);
    }

    private function isParticipant(User $user, HelpOffer $offer): bool
    {
        return $this->isHelper($user, $offer) || $this->isOwner($user, $offer);
    }

    private function isHelper(User $user, HelpOffer $offer): bool
    {
        return (string) $offer->helper_user_id === (string) $user->id;
    }

    private function isOwner(User $user, HelpOffer $offer): bool
    {
        if ((string) $offer->post_owner_id === (string) $user->id) return true;
        $post = $offer->relationLoaded('post') ? $offer->post : $offer->post()->first();
        return $post instanceof Post && $this->belongsToOrganization($user, $post);
    }

    private function belongsToOrganization(User $user, Post $post): bool
    {
        return filled($user->organization_id)
            && filled($post->organization_id)
            && (string) $user->organization_id === (string) $post->organization_id;
    }
}
