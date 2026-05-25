<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // TODO: Implement report listing with status and severity filtering
    }

    public function show(Report $report)
    {
        // TODO: Implement report detail view
    }

    public function claim(Request $request, Report $report)
    {
        // TODO: Implement claim report (new -> in_progress)
    }

    public function requestInfo(Request $request, Report $report)
    {
        // TODO: Implement request info (in_progress -> waiting_response)
    }

    public function close(Request $request, Report $report)
    {
        // TODO: Implement close report (in_progress/waiting_response -> closed)
    }
}
