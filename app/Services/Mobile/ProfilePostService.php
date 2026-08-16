<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ProfilePostService
{
    /**
     * @param  array{perPage?: int|string|null, status?: string|null, sort?: string|null}  $params
     */
    public function paginate(User $user, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));

        $query = Post::query()
            ->with([
                'organization',
                'campaign',
                'author',
                'images',
                'saves' => static fn (Builder $builder) => $builder->where('user_id', $user->id),
                'campaignApplications' => static fn (Builder $builder) => $builder->where('created_by', $user->id),
            ])
            ->where('author_id', $user->id)
            ->whereIn('status', ['published', 'rejected', 'archived']);

        if (filled($params['status'] ?? null)) {
            $query->where('status', $this->internalStatus((string) $params['status']));
        }

        if (($params['sort'] ?? 'newest') === 'oldest') {
            $query->orderBy('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->orderBy('id')->paginate($perPage);
    }

    private function internalStatus(string $profileStatus): string
    {
        return match ($profileStatus) {
            'posted' => 'published',
            'unposted' => 'rejected',
            'archived' => 'archived',
        };
    }
}
