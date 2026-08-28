<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Organization;
use App\Models\PublisherFollow;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FollowService
{
    public function resolveTarget(string $type, string $id): Organization|User
    {
        if (! in_array($type, [PublisherFollow::TARGET_USER, PublisherFollow::TARGET_ORGANIZATION], true)) {
            throw ValidationException::withMessages(['targetType' => ['Target type must be user or organization.']]);
        }

        $target = $type === PublisherFollow::TARGET_USER
            ? User::query()->with('avatarMedia')->whereKey($id)->where('status', 'active')->first()
            : Organization::query()->with('logoMedia')->whereKey($id)->where('status', 'active')->first();

        abort_if($target === null, 404, 'Publisher not found.');

        return $target;
    }

    public function follow(User $actor, string $type, string $id): PublisherFollow
    {
        $this->resolveTarget($type, $id);

        if ($type === PublisherFollow::TARGET_USER && (string) $actor->id === $id) {
            throw ValidationException::withMessages(['targetId' => ['You cannot follow yourself.']]);
        }

        $attributes = [
            'follower_user_id' => (string) $actor->id,
            'target_type' => $type,
            'target_id' => $id,
        ];

        PublisherFollow::query()->insertOrIgnore([[
            'id' => (string) Str::uuid(),
            ...$attributes,
            'notification_level' => PublisherFollow::NOTIFICATION_ALL,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        return PublisherFollow::query()->where($attributes)->firstOrFail();
    }

    public function unfollow(User $actor, string $type, string $id): void
    {
        if (! in_array($type, [PublisherFollow::TARGET_USER, PublisherFollow::TARGET_ORGANIZATION], true)) {
            throw ValidationException::withMessages(['targetType' => ['Target type must be user or organization.']]);
        }

        PublisherFollow::query()
            ->where('follower_user_id', $actor->id)
            ->where('target_type', $type)
            ->where('target_id', $id)
            ->delete();
    }

    public function isFollowing(?User $actor, string $type, string $id): bool
    {
        return $actor !== null && PublisherFollow::query()
            ->where('follower_user_id', $actor->id)
            ->where('target_type', $type)
            ->where('target_id', $id)
            ->exists();
    }

    public function followersCount(string $type, string $id): int
    {
        return PublisherFollow::query()->where('target_type', $type)->where('target_id', $id)->count();
    }

    public function following(User $actor, string $type = 'all', int $perPage = 20): LengthAwarePaginator
    {
        if (! in_array($type, ['all', PublisherFollow::TARGET_USER, PublisherFollow::TARGET_ORGANIZATION], true)) {
            throw ValidationException::withMessages(['type' => ['Type must be all, user, or organization.']]);
        }

        $paginator = PublisherFollow::query()
            ->where('follower_user_id', $actor->id)
            ->when($type !== 'all', fn ($query) => $query->where('target_type', $type))
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, min($perPage, 100)));

        $follows = collect($paginator->items());
        $users = $this->targets($follows, PublisherFollow::TARGET_USER, User::class, 'avatarMedia');
        $organizations = $this->targets($follows, PublisherFollow::TARGET_ORGANIZATION, Organization::class, 'logoMedia');
        $counts = PublisherFollow::query()
            ->selectRaw('target_type, target_id, COUNT(*) as aggregate')
            ->where(function ($query) use ($follows): void {
                foreach ($follows->groupBy('target_type') as $targetType => $group) {
                    $query->orWhere(function ($targetQuery) use ($targetType, $group): void {
                        $targetQuery->where('target_type', $targetType)->whereIn('target_id', $group->pluck('target_id'));
                    });
                }
            })
            ->groupBy('target_type', 'target_id')
            ->get()
            ->keyBy(fn ($row) => $row->target_type.':'.$row->target_id);

        $paginator->setCollection($follows->map(function (PublisherFollow $follow) use ($users, $organizations, $counts) {
            $target = $follow->target_type === PublisherFollow::TARGET_USER
                ? $users->get((string) $follow->target_id)
                : $organizations->get((string) $follow->target_id);

            if ($target === null) {
                return null;
            }

            $count = $counts->get($follow->target_type.':'.$follow->target_id);
            $target->setAttribute('followers_count', (int) ($count?->aggregate ?? 0));
            $target->setAttribute('is_following', true);

            return $target;
        })->filter()->values());

        return $paginator;
    }

    private function targets(Collection $follows, string $type, string $model, string $relation): Collection
    {
        $ids = $follows->where('target_type', $type)->pluck('target_id')->values();
        if ($ids->isEmpty()) return collect();

        return $model::query()
            ->with($relation)
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->get()
            ->keyBy(fn ($target) => (string) $target->id);
    }
}
