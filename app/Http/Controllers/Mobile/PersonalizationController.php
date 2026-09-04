<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\OnboardingRequest;
use App\Http\Requests\Mobile\PersonalizationCapabilitiesRequest;
use App\Http\Requests\Mobile\PersonalizationInterestsRequest;
use App\Http\Requests\Mobile\PersonalizationPreferencesRequest;
use App\Models\User;
use App\Services\Mobile\PersonalizationService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalizationController extends Controller
{
    public function __construct(private readonly PersonalizationService $personalization) {}

    public function options(): JsonResponse
    {
        return MobileApiResponse::success(
            $this->personalization->options(),
            'Onboarding options retrieved successfully.',
        );
    }

    public function onboarding(OnboardingRequest $request): JsonResponse
    {
        return MobileApiResponse::success(
            $this->personalization->completeOnboarding($this->user($request), $request->validated()),
            'Personalization onboarding completed successfully.',
        );
    }

    public function show(Request $request): JsonResponse
    {
        return MobileApiResponse::success(
            $this->personalization->profile($this->user($request)),
            'Personalization preferences retrieved successfully.',
        );
    }

    public function updatePreferences(PersonalizationPreferencesRequest $request): JsonResponse
    {
        return MobileApiResponse::success(
            $this->personalization->updatePreferences($this->user($request), $request->validated()),
            'Personalization preferences updated successfully.',
        );
    }

    public function updateInterests(PersonalizationInterestsRequest $request): JsonResponse
    {
        return MobileApiResponse::success(
            $this->personalization->updateInterests($this->user($request), $request->validated('categoryIds')),
            'Interests updated successfully.',
        );
    }

    public function updateCapabilities(PersonalizationCapabilitiesRequest $request): JsonResponse
    {
        return MobileApiResponse::success(
            $this->personalization->updateCapabilities($this->user($request), $request->validated('capabilityIds')),
            'Capabilities updated successfully.',
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
