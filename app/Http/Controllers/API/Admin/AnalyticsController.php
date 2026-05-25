<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function kpis(Request $request, AnalyticsService $service): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $request->validate(['range' => ['sometimes', 'in:7d,30d,90d,12m']]);

        $range = $request->get('range', '7d');
        $kpis = $service->getKpis($range);

        return response()->json([
            'data' => $kpis,
            'message' => 'KPIs retrieved successfully',
        ]);
    }

    public function weekly(Request $request, AnalyticsService $service): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $request->validate(['range' => ['sometimes', 'in:7d,30d,90d,12m']]);

        $range = $request->get('range', '7d');
        $weekly = $service->getWeeklyStats($range);

        return response()->json([
            'data' => $weekly,
            'message' => 'Weekly stats retrieved successfully',
        ]);
    }
}
