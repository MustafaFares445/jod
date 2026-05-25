<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', 'org-dashboard');

        return response()->json([
            'data' => [
                'stats' => [
                    ['id' => 'campaigns', 'label' => 'Active Campaigns', 'value' => 5, 'hint' => '2 closed'],
                    ['id' => 'posts', 'label' => 'Published Posts', 'value' => 23, 'hint' => '+3 this week'],
                    ['id' => 'donors', 'label' => 'Total Donors', 'value' => 142, 'hint' => '+12 this month'],
                    ['id' => 'raised', 'label' => 'Amount Raised', 'value' => 15750, 'hint' => '$5,250 this month'],
                ],
                'activity' => [
                    ['id' => 1, 'title' => 'Campaign Approved', 'detail' => 'Health Initiative 2024', 'at' => now()],
                    ['id' => 2, 'title' => 'New Donation', 'detail' => 'JOD 500 from Anonymous', 'at' => now()->subHours(2)],
                ],
            ],
        ]);
    }
}
