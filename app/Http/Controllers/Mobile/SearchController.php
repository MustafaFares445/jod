<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\GlobalSearchRequest;
use App\Http\Resources\Mobile\MobileCampaignResource;
use App\Http\Resources\Mobile\MobileHomePostResource;
use App\Http\Resources\Mobile\MobilePublisherResource;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request): JsonResponse
    {
        $params = $request->validated();
        $type = (string) ($params['type'] ?? 'all');
        $limit = (int) ($params['perType'] ?? 10);
        $viewer = $request->user('sanctum');
        $viewer = $viewer instanceof User ? $viewer : null;

        [$accounts, $accountsTotal] = in_array($type, ['all', 'accounts'], true)
            ? $this->accounts($params, $limit, $request)
            : [[], 0];
        [$posts, $postsTotal] = in_array($type, ['all', 'posts'], true)
            ? $this->posts($params, $limit, $request, $viewer)
            : [[], 0];
        [$campaigns, $campaignsTotal] = in_array($type, ['all', 'campaigns'], true)
            ? $this->campaigns($params, $limit, $request)
            : [[], 0];

        return MobileApiResponse::success([
            'accounts' => $accounts,
            'posts' => $posts,
            'campaigns' => $campaigns,
        ], 'Search results retrieved successfully.', [
            'counts' => [
                'accounts' => $accountsTotal,
                'posts' => $postsTotal,
                'campaigns' => $campaignsTotal,
            ],
            'appliedFilters' => [
                'search' => $params['search'] ?? null,
                'type' => $type,
                'location' => $params['location'] ?? null,
                'category' => $params['category'] ?? null,
                'sort' => $params['sort'] ?? 'newest',
            ],
        ]);
    }

    /** @return array{0: list<array<string, mixed>>, 1: int} */
    private function accounts(array $params, int $limit, GlobalSearchRequest $request): array
    {
        $search = trim((string) ($params['search'] ?? ''));
        $location = trim((string) ($params['location'] ?? ''));

        $organizations = Organization::query()
            ->with('logoMedia')
            ->where('status', 'active')
            ->whereHas('posts', fn (Builder $post) => $post->where('status', 'published'))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($location !== '', fn (Builder $builder) => $builder->where('location', 'like', "%{$location}%"));

        $users = User::query()
            ->where('status', 'active')
            ->whereHas('posts', function (Builder $post): void {
                $post->where('status', 'published')
                    ->whereNull('organization_id');
            })
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('bio', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($location !== '', fn (Builder $builder) => $builder->where('city', 'like', "%{$location}%"));

        $total = $organizations->count() + $users->count();

        $results = $organizations->limit($limit)->get()
            ->map(fn (Organization $organization): array => [
                'accountType' => 'organization',
                ...MobilePublisherResource::make($organization)->resolve($request),
            ])
            ->concat(
                $users->limit($limit)->get()->map(fn (User $user): array => [
                    'accountType' => 'user',
                    ...MobilePublisherResource::make($user)->resolve($request),
                ])
            )
            ->sortBy(fn (array $account): string => mb_strtolower((string) ($account['name'] ?? '')))
            ->take($limit)
            ->values()
            ->all();

        return [$results, $total];
    }

    /** @return array{0: list<array<string, mixed>>, 1: int} */
    private function posts(array $params, int $limit, GlobalSearchRequest $request, ?User $viewer): array
    {
        $search = trim((string) ($params['search'] ?? ''));
        $location = trim((string) ($params['location'] ?? ''));
        $category = trim((string) ($params['category'] ?? ''));
        $sort = (string) ($params['sort'] ?? 'newest');

        $query = Post::query()
            ->with($this->postRelations($viewer))
            ->where('status', 'published')
            ->when($location !== '', fn (Builder $builder) => $builder->where('location', 'like', "%{$location}%"))
            ->when($category !== '', function (Builder $builder) use ($category): void {
                $builder->where(function (Builder $inner) use ($category): void {
                    $inner->where('category_id', $category)
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', $category));
                });
            })
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhereHas('organization', fn (Builder $organization) => $organization->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('author', fn (Builder $author) => $author->where('name', 'like', "%{$search}%"));
                });
            });

        $total = $query->count();
        $sort === 'oldest'
            ? $query->orderBy('published_at')->orderBy('created_at')
            : $query->orderByDesc('published_at')->orderByDesc('created_at');

        $results = $query->limit($limit)->get()
            ->map(fn (Post $post): array => MobileHomePostResource::make($post)->resolve($request))
            ->all();

        return [$results, $total];
    }

    /** @return array{0: list<array<string, mixed>>, 1: int} */
    private function campaigns(array $params, int $limit, GlobalSearchRequest $request): array
    {
        $search = trim((string) ($params['search'] ?? ''));
        $location = trim((string) ($params['location'] ?? ''));
        $category = trim((string) ($params['category'] ?? ''));
        $sort = (string) ($params['sort'] ?? 'newest');

        $query = Campaign::query()
            ->with([
                'organization.logoMedia',
                'creator',
                'imageMedia',
                'posts' => static fn (Relation $relation) => $relation
                    ->where('status', 'published')
                    ->orderByDesc('published_at')
                    ->orderByDesc('created_at')
                    ->with('images'),
            ])
            ->where('status', 'active')
            ->when($location !== '', fn (Builder $builder) => $builder->where('location', 'like', "%{$location}%"))
            ->when($category !== '', fn (Builder $builder) => $builder->where('category', $category))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhereHas('organization', fn (Builder $organization) => $organization->where('name', 'like', "%{$search}%"));
                });
            });

        $total = $query->count();
        $sort === 'oldest'
            ? $query->orderBy('created_at')
            : $query->orderByDesc('created_at');

        $results = $query->limit($limit)->get()
            ->map(fn (Campaign $campaign): array => MobileCampaignResource::make($campaign)->resolve($request))
            ->all();

        return [$results, $total];
    }

    /** @return array<int|string, mixed> */
    private function postRelations(?User $viewer): array
    {
        $relations = ['organization.logoMedia', 'campaign', 'category', 'author.avatarMedia', 'images'];

        if ($viewer === null) {
            return $relations;
        }

        $relations['likes'] = static fn (Relation $builder) => $builder->where('user_id', $viewer->id);
        $relations['saves'] = static fn (Relation $builder) => $builder->where('user_id', $viewer->id);
        $relations['campaignApplications'] = static fn (Relation $builder) => $builder->where('created_by', $viewer->id);

        return $relations;
    }
}
