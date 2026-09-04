<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\HelpRequestStatus;
use App\Enums\UserIntent;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminHelpMatchingService
{
    public function paginate(array $filters, int $perPage): mixed
    {
        $query = Post::query()->with(['category', 'author', 'organization'])->where('type', 'help_request')
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('help_status', $v))
            ->when($filters['urgency'] ?? null, fn (Builder $q, $v) => $q->where('urgency', $v))
            ->when($filters['categoryId'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($filters['city'] ?? null, fn (Builder $q, $v) => $q->where('location', 'like', '%'.$v.'%'))
            ->latest('created_at');
        return $query->paginate($perPage)->through(fn (Post $post) => $this->summary($post));
    }

    public function detail(Post $post): array
    {
        abort_unless($post->type === 'help_request', 404);
        $summary = $this->summary($post);
        $summary['matchingCriteria'] = ['categoryId' => $post->category_id, 'city' => $post->location, 'intent' => [UserIntent::Giver->value, UserIntent::Both->value]];
        $summary['offers'] = $post->helpOffers()->latest()->limit(50)->get()->map(fn ($offer) => ['id' => (string) $offer->id, 'status' => $offer->status?->value ?? $offer->status, 'userId' => (string) $offer->user_id, 'createdAt' => $offer->created_at?->toIso8601String()])->all();
        return $summary;
    }

    private function summary(Post $post): array
    {
        $potentialHelpers = User::query()->whereHas('preference', function (Builder $q) use ($post): void {
            $q->whereIn('intent', [UserIntent::Giver->value, UserIntent::Both->value]);
            if (filled($post->location)) $q->where(fn (Builder $location) => $location->where('preferred_city', $post->location)->orWhere('remote_help_enabled', true));
        })->when($post->category_id, fn (Builder $q) => $q->whereHas('categoryInterests', fn (Builder $interest) => $interest->where('category_id', $post->category_id)->where(fn (Builder $w) => $w->where('explicit_weight', '>', 0)->orWhere('behavioral_weight', '>', 0))))->whereKeyNot($post->author_id)->count();

        return [
            'request' => ['id' => (string) $post->id, 'title' => (string) ($post->title ?? ''), 'category' => $post->category ? ['id' => (string) $post->category->id, 'name' => (string) $post->category->name] : null, 'city' => $post->location, 'urgency' => $post->urgency?->value ?? $post->urgency, 'status' => $post->help_status?->value ?? $post->help_status, 'expiresAt' => $post->expires_at?->toIso8601String()],
            'potentialHelpersCount' => $potentialHelpers,
            'offersCount' => $post->helpOffers()->count(),
            'activeOffersCount' => $post->activeHelpOffers()->count(),
            'completedOffersCount' => $post->completedHelpOffers()->count(),
            'fulfilled' => $post->help_status === HelpRequestStatus::Fulfilled,
        ];
    }
}
