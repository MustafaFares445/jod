<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\ExplorationFeedbackRequest;
use App\Models\User;
use App\Services\Mobile\ExplorationFeedbackService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;

class ExplorationFeedbackController extends Controller
{
    public function __construct(private readonly ExplorationFeedbackService $service) {}

    public function __invoke(ExplorationFeedbackRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return MobileApiResponse::success(
            $this->service->submit($user, $request->validated()),
            'Exploration feedback recorded successfully.',
        );
    }
}
