<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Models\PostPollOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class GroupPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = $this->relationLoaded('author') ? $this->author : null;
        $group = $this->relationLoaded('group') ? $this->group : null;
        $membership = $group?->relationLoaded('memberships') && $author
            ? $group->memberships->firstWhere('user_id', $author->id)
            : null;
        $poll = $this->relationLoaded('poll') ? $this->poll : null;
        $selectedOptionIds = $poll?->relationLoaded('votes')
            ? $poll->votes->pluck('option_id')->map(fn ($id) => (string) $id)->values()->all()
            : [];
        $totalVotes = $poll?->relationLoaded('options') ? (int) $poll->options->sum('votes_count') : 0;

        return [
            'id' => (string) $this->id,
            'groupId' => (string) $this->group_id,
            'author' => $author ? [
                'id' => (string) $author->id,
                'name' => (string) $author->name,
                'username' => filled($author->email) ? Str::before((string) $author->email, '@') : 'jod',
                'avatarUrl' => $author->relationLoaded('avatarMedia') ? $author->avatarMedia?->publicUrl() : null,
                'role' => $membership?->role ?? 'member',
            ] : null,
            'title' => $this->title,
            'body' => (string) ($this->content ?? ''),
            'type' => (string) $this->type,
            'status' => $this->group_review_status ?? ($this->status === 'published' ? 'published' : $this->status),
            'rejectionReason' => $this->group_rejection_reason ?? $this->block_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
            'createdAtLabel' => $this->created_at?->diffForHumans() ?? '',
            'likesCount' => (int) $this->reactions_count,
            'commentsCount' => (int) ($this->group_comments_count ?? $this->groupComments()->where('status', 'published')->count()),
            'isLiked' => $this->relationLoaded('likes') && $this->likes->isNotEmpty(),
            'isPinned' => false,
            'images' => $this->relationLoaded('images') ? $this->images->map(fn ($media) => $media->publicUrl())->values()->all() : [],
            'poll' => $poll ? [
                'id' => (string) $poll->id,
                'question' => (string) $poll->question,
                'allowsMultipleChoices' => (bool) $poll->allows_multiple_choices,
                'endsAt' => $poll->ends_at?->toIso8601String(),
                'totalVotes' => $totalVotes,
                'selectedOptionIds' => $selectedOptionIds,
                'options' => $poll->options->map(fn (PostPollOption $option) => [
                    'id' => (string) $option->id,
                    'label' => (string) $option->label,
                    'votesCount' => (int) $option->votes_count,
                    'percentage' => $totalVotes > 0 ? round(((int) $option->votes_count / $totalVotes) * 100, 1) : 0,
                ])->values()->all(),
            ] : null,
        ];
    }
}
