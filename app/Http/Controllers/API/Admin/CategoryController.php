<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Data\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(protected CategoryService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $query = Category::query()
            ->when(($target = $this->queryParam($request, 'filter.target')) && $target !== 'all', fn (Builder $builder) => $builder->where('target', $target))
            ->when(($status = $this->queryParam($request, 'filter.status')) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($search = $this->queryParam($request, 'filter.search')) && $search !== 'all', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');

        return CategoryResource::collection($query->paginate($perPage));
    }

    public function store(CategoryRequest $request): CategoryResource
    {
        $this->authorize('create', Category::class);

        $category = $this->service->store(CategoryData::from($request->validated()));

        return CategoryResource::make($category);
    }

    public function show(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        return CategoryResource::make($category);
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $this->authorize('update', $category);

        $updated = $this->service->update(CategoryData::from($request->validated()), $category);

        return CategoryResource::make($updated);
    }

    public function updateStatus(Request $request, Category $category): CategoryResource
    {
        $this->authorize('update', $category);

        $data = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        $updated = $this->service->updateStatus($category, $data['status']);

        return CategoryResource::make($updated);
    }

    public function destroy(Category $category): Response
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }

    private function queryParam(Request $request, string $key): mixed
    {
        $queryParams = $request->query();

        if (array_key_exists($key, $queryParams)) {
            return $queryParams[$key];
        }

        $flatKey = str_replace('.', '_', $key);
        if (array_key_exists($flatKey, $queryParams)) {
            return $queryParams[$flatKey];
        }

        return data_get($queryParams, $key);
    }
}
