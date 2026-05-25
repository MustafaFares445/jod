<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\OverviewService;
use Illuminate\Http\JsonResponse;

class OverviewController extends Controller
{
    public function __invoke(OverviewService $service): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $stats = $service->getStats();
        $activity = $service->getActivity();

        return response()->json([
            'data' => array_merge($stats, $activity),
            'message' => 'Overview retrieved successfully',
        ]);
    }
}
