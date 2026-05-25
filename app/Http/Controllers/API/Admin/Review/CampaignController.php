<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin\Review;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        // TODO: Implement campaign review listing
    }

    public function show(Campaign $campaign)
    {
        // TODO: Implement campaign detail view
    }

    public function approve(Request $request, Campaign $campaign)
    {
        // TODO: Implement campaign approval
    }

    public function reject(Request $request, Campaign $campaign)
    {
        // TODO: Implement campaign rejection
    }
}
