<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Services\OrganizationRecommendationAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecommendationAnalyticsController extends Controller
{
    public function __construct(private readonly OrganizationRecommendationAnalyticsService $service) {}

    public function recommendations(Request $request): JsonResponse
    {
        Gate::authorize('org-dashboard');

        return response()->json([
            'data' => $this->service->analytics($this->organizationId(), $this->filters($request)),
        ]);
    }

    public function content(Request $request): JsonResponse
    {
        Gate::authorize('org-dashboard');

        return response()->json([
            'data' => $this->service->contentPerformance($this->organizationId(), $this->filters($request)),
        ]);
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
            'contentType' => ['nullable', Rule::in(['post', 'campaign', 'video'])],
            'categoryId' => ['nullable', 'string', Rule::exists('categories', 'id')],
            'postType' => ['nullable', 'string', Rule::in([
                'general', 'job_opportunity', 'campaign_teaser', 'campaign_update', 'campaign_summary',
                'service_offer', 'volunteer_opportunity', 'awareness', 'help_request',
            ])],
        ]);

        if (isset($validated['postType']) && (($validated['contentType'] ?? 'post') !== 'post')) {
            throw ValidationException::withMessages([
                'postType' => ['postType can only be used when contentType is post.'],
            ]);
        }

        return $validated;
    }

    private function organizationId(): string
    {
        $id = (string) auth()->user()?->organization_id;
        if ($id === '') {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $id;
    }
}
