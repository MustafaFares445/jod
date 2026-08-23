<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class BriefController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $categories->map(static fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values(),
        ]);
    }

    public function campaigns(): JsonResponse
    {
        $organizationId = (string) auth()->user()?->organization_id;
        abort_if($organizationId === '', 403, 'Authenticated user is not linked to an organization.');

        $campaigns = Campaign::query()
            ->where('organization_id', $organizationId)
            ->orderBy('title')
            ->get(['id', 'title']);

        return response()->json([
            'data' => $campaigns->map(static fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->title,
            ])->values(),
        ]);
    }
}
