<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAnyOrganization', Report::class);

        return ReportResource::collection(
            $this->service->paginate($request->query(), $this->organizationId()),
        );
    }

    public function show(Report $report): ReportResource
    {
        $this->authorize('viewOrganization', $report);

        return ReportResource::make($report->loadMissing(['organization', 'reporter', 'assignee']));
    }

    public function claim(Request $request, Report $report): ReportResource
    {
        $this->authorize('claim', $report);

        $data = $request->validate([
            'assigneeId' => ['nullable', 'string', 'exists:users,id'],
        ]);

        $report = $this->service->claim(
            $report,
            $data['assigneeId'] ?? auth()->id(),
            (string) $request->user()->name,
        );

        return ReportResource::make($report->refresh()->loadMissing(['organization', 'reporter', 'assignee']));
    }

    public function requestInfo(Request $request, Report $report): ReportResource
    {
        $this->authorize('requestInfo', $report);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = $this->service->requestInfo(
            $report,
            $data['note'] ?? null,
            (string) $request->user()->name,
        );

        return ReportResource::make($report->refresh()->loadMissing(['organization', 'reporter', 'assignee']));
    }

    public function close(Request $request, Report $report): ReportResource
    {
        $this->authorize('close', $report);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = $this->service->close(
            $report,
            $data['note'] ?? null,
            (string) $request->user()->name,
        );

        return ReportResource::make($report->refresh()->loadMissing(['organization', 'reporter', 'assignee']));
    }

    private function organizationId(): string
    {
        $organizationId = (string) auth()->user()?->organization_id;
        if ($organizationId === '') {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $organizationId;
    }
}
