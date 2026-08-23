<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationIsActive
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->user()?->organization;

        if ($organization === null) {
            return $this->errorResponse('Authenticated user is not linked to an organization.', 403);
        }

        if ($organization->status !== 'active') {
            return $this->errorResponse('Organization must be active and verified to access dashboard APIs.', 403);
        }

        $isOnboardingSettingsRoute = $request->is('api/v1/org/settings/*')
            || $request->is('api/v1/org/profile');

        if ($organization->verification_status !== 'verified' && ! $isOnboardingSettingsRoute) {
            return $this->errorResponse('Organization must be active and verified to access dashboard APIs.', 403);
        }

        return $next($request);
    }
}
