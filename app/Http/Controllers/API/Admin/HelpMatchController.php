<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\HelpMatchResource;
use App\Models\HelpOffer;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HelpMatchController extends Controller
{
    private const RELATIONS = [
        'post.category',
        'helper.capabilities',
        'helper.preference',
        'helper.categoryInterests',
        'postOwner',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeView($request);
        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $status = $this->queryParam($request, 'filter.status');
        $type = $this->queryParam($request, 'filter.type');
        $urgency = $this->queryParam($request, 'filter.urgency');
        $search = trim((string) ($this->queryParam($request, 'filter.search') ?? ''));
        $staleOnly = filter_var($this->queryParam($request, 'filter.staleOnly') ?? false, FILTER_VALIDATE_BOOL);
        $sort = (string) ($request->query('sort') ?: '-createdAt');

        $query = HelpOffer::query()
            ->with(self::RELATIONS)
            ->when(filled($status) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(filled($type) && $type !== 'all', fn (Builder $builder) => $builder->where('type', $type))
            ->when(filled($urgency) && $urgency !== 'all', fn (Builder $builder) => $builder->whereHas('post', fn (Builder $post) => $post->where('urgency', $urgency)))
            ->when($staleOnly, fn (Builder $builder) => $builder
                ->whereIn('status', ['pending', 'accepted', 'contacting'])
                ->where('updated_at', '<=', now()->subHours(24)))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->whereHas('post', fn (Builder $post) => $post->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('helper', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('postOwner', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            });

        match ($sort) {
            'createdAt' => $query->orderBy('created_at'),
            '-createdAt' => $query->orderByDesc('created_at'),
            'updatedAt' => $query->orderBy('updated_at'),
            '-updatedAt' => $query->orderByDesc('updated_at'),
            default => $query->orderByDesc('created_at'),
        };

        return HelpMatchResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, HelpOffer $helpMatch): HelpMatchResource
    {
        $this->authorizeView($request);
        return HelpMatchResource::make($helpMatch->loadMissing(self::RELATIONS));
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionNameResolver::resolve(PermissionGroup::HELP_MATCH, PermissionAction::VIEW)), 403);
    }

    private function queryParam(Request $request, string $key): mixed
    {
        $query = $request->query();
        if (array_key_exists($key, $query)) return $query[$key];
        $flat = str_replace('.', '_', $key);
        return $query[$flat] ?? data_get($query, $key);
    }
}
