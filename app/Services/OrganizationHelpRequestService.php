<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HelpRequestStatus;
use App\Models\AuditLog;
use App\Models\Post;
use App\Models\User;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationHelpRequestService
{
    public function paginate(string $organizationId, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $search = SearchFilter::fromArray($params);

        return Post::query()
            ->with(['category', 'requiredCapabilities', 'author'])
            ->withCount(['helpOffers', 'activeHelpOffers', 'completedHelpOffers'])
            ->where('organization_id', $organizationId)
            ->where('type', 'help_request')
            ->when(filled($params['status'] ?? null), fn (Builder $q, $v) => $q->where('help_status', $v))
            ->when(filled($params['urgency'] ?? null), fn (Builder $q, $v) => $q->where('urgency', $v))
            ->when(filled($params['categoryId'] ?? null), fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when(filled($params['location'] ?? null), fn (Builder $q, $v) => $q->where('location', 'like', '%'.$v.'%'))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('summary', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")))
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function find(string $organizationId, Post $post): Post
    {
        abort_unless($post->type === 'help_request' && (string) $post->organization_id === $organizationId, 404);
        return $post->loadMissing(['organization', 'category', 'requiredCapabilities', 'author'])
            ->loadCount(['helpOffers', 'activeHelpOffers', 'completedHelpOffers']);
    }

    public function updateStatus(User $actor, Post $post, HelpRequestStatus $status): Post
    {
        $organizationId = (string) $actor->organization_id;
        abort_unless($post->type === 'help_request' && $organizationId !== '' && (string) $post->organization_id === $organizationId, 404);
        if ($status === HelpRequestStatus::Expired) throw ValidationException::withMessages(['status' => ['Expired status is managed automatically by the system.']]);
        if ($post->help_status?->isTerminal()) throw ValidationException::withMessages(['status' => ['A completed help request cannot be reopened from the organization dashboard.']]);

        $allowed = match ($post->help_status ?? HelpRequestStatus::Open) {
            HelpRequestStatus::Open => [HelpRequestStatus::Open, HelpRequestStatus::InProgress, HelpRequestStatus::Fulfilled, HelpRequestStatus::PartiallyFulfilled, HelpRequestStatus::NotFulfilled],
            HelpRequestStatus::InProgress => [HelpRequestStatus::Open, HelpRequestStatus::InProgress, HelpRequestStatus::Fulfilled, HelpRequestStatus::PartiallyFulfilled, HelpRequestStatus::NotFulfilled],
            default => [],
        };
        if (! in_array($status, $allowed, true)) throw ValidationException::withMessages(['status' => ['Invalid help request status transition.']]);

        return DB::transaction(function () use ($actor, $post, $status): Post {
            $locked = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $old = $locked->help_status?->value ?? $locked->help_status;
            $locked->forceFill([
                'help_status' => $status,
                'fulfilled_at' => $status === HelpRequestStatus::Fulfilled ? now() : null,
                'updated_by' => $actor->id,
            ])->save();
            AuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'action' => 'help_request.status_changed',
                'entity_type' => 'post',
                'entity_id' => (string) $locked->id,
                'metadata' => ['organizationId' => (string) $actor->organization_id, 'old' => ['helpStatus' => $old], 'new' => ['helpStatus' => $status->value], 'source' => 'org-dashboard'],
                'at' => now(),
            ]);
            return $this->find((string) $actor->organization_id, $locked->refresh());
        });
    }
}
