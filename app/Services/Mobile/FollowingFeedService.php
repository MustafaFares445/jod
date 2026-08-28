<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\MediaModel;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Post;
use App\Models\PublisherFollow;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class FollowingFeedService
{
    public function paginate(User $viewer, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));
        $userIds = PublisherFollow::query()->where('follower_user_id', $viewer->id)->where('target_type', 'user')->pluck('target_id');
        $organizationIds = PublisherFollow::query()->where('follower_user_id', $viewer->id)->where('target_type', 'organization')->pluck('target_id');

        $posts = Post::query()
            ->with(['organization.logoMedia', 'campaign', 'category', 'author.avatarMedia', 'images', 'videos'])
            ->where('status', 'published')
            ->where(function ($query) use ($userIds, $organizationIds): void {
                $query->where(function ($userPosts) use ($userIds): void {
                    $userPosts->whereNull('organization_id')->whereIn('author_id', $userIds);
                })->orWhereIn('organization_id', $organizationIds);
            })
            ->get()
            ->map(fn (Post $post) => ['contentType' => 'post', 'sortAt' => $post->published_at ?? $post->created_at, 'model' => $post]);

        $campaigns = Campaign::query()
            ->with(['organization.logoMedia', 'imageMedia', 'category'])
            ->where('status', 'active')
            ->whereIn('organization_id', $organizationIds)
            ->get()
            ->map(fn (Campaign $campaign) => ['contentType' => 'campaign', 'sortAt' => $campaign->created_at, 'model' => $campaign]);

        $videos = Media::query()
            ->with('organization.logoMedia')
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('prop', 'videos')
            ->whereIn('model_id', $organizationIds)
            ->get()
            ->map(fn (Media $video) => ['contentType' => 'video', 'sortAt' => $video->created_at, 'model' => $video]);

        $all = $posts->concat($campaigns)->concat($videos)->sortByDesc('sortAt')->values();
        $total = $all->count();
        $slice = $all->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
