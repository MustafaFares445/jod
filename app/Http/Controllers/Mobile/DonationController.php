<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\DonationHistoryRequest;
use App\Http\Requests\Mobile\DonationRequest;
use App\Http\Resources\Mobile\DonationResource;
use App\Models\User;
use App\Services\Mobile\DonationService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    public function __construct(private readonly DonationService $service) {}

    public function store(DonationRequest $request, string $campaign): JsonResponse
    {
        $donation = $this->service->createIntent(
            $this->user($request),
            $campaign,
            $request->validated(),
        );

        return MobileApiResponse::success(
            DonationResource::make($donation)->resolve($request),
            'Donation intent created successfully. The campaign amount is not updated until the organization confirms receipt.',
            201,
        );
    }

    public function index(DonationHistoryRequest $request): JsonResponse
    {
        $paginator = $this->service->paginateForUser(
            $this->user($request),
            $request->validated(),
        );

        return MobileApiResponse::paginated(
            $paginator->through(fn ($donation) => DonationResource::make($donation)->resolve($request)),
            'Donations retrieved successfully.',
        );
    }

    public function show(Request $request, string $donation): JsonResponse
    {
        $model = $this->service->findForUser($this->user($request), $donation);

        if ($model === null) {
            return MobileApiResponse::error('not_found', 'The requested donation could not be found.', null, 404);
        }

        return MobileApiResponse::success(
            DonationResource::make($model)->resolve($request),
            'Donation retrieved successfully.',
        );
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
