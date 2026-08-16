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

    /**
     * Record a contribution to an active campaign.
     *
     * This endpoint records a contribution in JOD. It does not charge a card or invoke an external payment provider.
     *
     * @urlParam campaign string required The active campaign identifier.
     * @bodyParam amount number required Contribution amount. Example: 25.50
     * @bodyParam paymentMethod string required Declared payment method. Allowed: credit_card, bank_transfer, cash, other.
     * @bodyParam phone string optional Contact phone override.
     * @bodyParam city string optional Donor city.
     *
     * @response array{success: true, message: string, data: array{id: string, campaignId: string, campaignTitle: string, organizationName: string|null, amount: float, paymentMethod: string|null, phone: string|null, city: string|null, source: string|null, donatedAt: string|null, createdAt: string|null}, error: null, meta: object}
     */
    public function store(DonationRequest $request, string $campaign): JsonResponse
    {
        $donation = $this->service->record(
            $this->user($request),
            $campaign,
            $request->validated(),
        );

        return MobileApiResponse::success(
            DonationResource::make($donation)->resolve($request),
            'Donation recorded successfully.',
        );
    }

    /**
     * List the authenticated user's donation history.
     *
     * @queryParam page int optional The page number.
     * @queryParam perPage int optional The number of donations per page, up to 100.
     * @queryParam campaignId string optional Filter history to one campaign.
     *
     * @response array{success: true, message: string, data: array<int, array{id: string, campaignId: string, campaignTitle: string, organizationName: string|null, amount: float, paymentMethod: string|null, phone: string|null, city: string|null, source: string|null, donatedAt: string|null, createdAt: string|null}>, error: null, meta: array{currentPage: int, perPage: int, total: int, lastPage: int}}
     */
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

    /**
     * Show one donation belonging to the authenticated user.
     *
     * @urlParam donation string required The donation identifier.
     *
     * @response array{success: true, message: string, data: array{id: string, campaignId: string, campaignTitle: string, organizationName: string|null, amount: float, paymentMethod: string|null, phone: string|null, city: string|null, source: string|null, donatedAt: string|null, createdAt: string|null}, error: null, meta: object}
     */
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
