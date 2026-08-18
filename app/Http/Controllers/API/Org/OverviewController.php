<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Services\OrganizationOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OverviewController extends Controller
{
    public function __invoke(Request $request, OrganizationOverviewService $service): JsonResponse
    {
        Gate::authorize('org-dashboard');
        $organization = $request->user()->organization;

        if ($organization === null) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        return $this->successResponse($service->overview($request->user(), $organization));
    }
}
