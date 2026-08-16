<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SavedPostService
{
    /**
     * @param  array{page?: int|string|null, perPage?: int|string|null}  $params
     */
    public function paginate(User $user, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));

        return SavedPost::query()
            ->with([
                'post.organization',
                'post.campaign',
                'post.author',
                'post.images',
                'post.campaignApplications' => static fn ($builder) => $builder->where('created_by', $user->id),
            ])
            ->where('user_id', $user->id)
            ->whereHas('post', fn ($query) => $query->where('status', 'published'))
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate($perPage);
    }
}
