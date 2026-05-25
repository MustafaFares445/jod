<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', 'org-reports');

        return response()->json([
            'data' => [
                ['id' => 1, 'subject' => 'Inappropriate Content', 'category' => 'content', 'status' => 'open', 'submittedAt' => now()->subDays(1)->toIso8601String()],
            ],
            'meta' => ['total' => 1, 'currentPage' => 1, 'perPage' => 20, 'lastPage' => 1],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('view', 'org-reports');

        return response()->json([
            'data' => ['id' => $id, 'subject' => 'Inappropriate Content', 'category' => 'content', 'status' => 'open'],
        ]);
    }
}
