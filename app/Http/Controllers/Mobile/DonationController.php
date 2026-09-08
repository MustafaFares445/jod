<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\DonationHistoryRequest;
use App\Http\Requests\Mobile\DonationRequest;
use App\Http\Resources\Mobile\DonationResource;
use App\Http\Resources\Mobile\PublicCampaignDonorResource;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\User;
use App\Services\Mobile\DonationService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    public function __construct(private readonly DonationService $service) {}

    public function campaignDonors(Request $request, string $campaign): JsonResponse
    {
        $validated = validator($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $campaignModel = Campaign::query()
            ->whereKey($campaign)
            ->where('status', 'active')
            ->first();

        if ($campaignModel === null) {
            return MobileApiResponse::error('not_found', 'The requested campaign could not be found.', null, 404);
        }

        $paginator = $this->service->paginatePublicForCampaign(
            $campaignModel,
            (int) ($validated['perPage'] ?? 20),
        );

        return MobileApiResponse::paginated(
            $paginator->through(fn (Donation $donation) => PublicCampaignDonorResource::make($donation)->resolve($request)),
            'Campaign donors retrieved successfully.',
        );
    }

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

    public function accept(Request $request, string $donation): JsonResponse
    {
        return MobileApiResponse::success(DonationResource::make($this->service->accept($this->user($request), $donation))->resolve($request), 'Donation accepted.');
    }

    public function contact(Request $request, string $donation): JsonResponse
    {
        return MobileApiResponse::success(DonationResource::make($this->service->markContacting($this->user($request), $donation))->resolve($request), 'Donation marked as contacting.');
    }

    public function agree(Request $request, string $donation): JsonResponse
    {
        return MobileApiResponse::success(DonationResource::make($this->service->markAgreed($this->user($request), $donation))->resolve($request), 'Donation marked as agreed.');
    }

    public function complete(Request $request, string $donation): JsonResponse
    {
        $data = $request->validate(['confirmedAmount' => ['required','numeric','gt:0','max:999999999.99']]);
        return MobileApiResponse::success(DonationResource::make($this->service->complete($this->user($request), $donation, (float) $data['confirmedAmount']))->resolve($request), 'Donation completed.');
    }

    public function cancel(Request $request, string $donation): JsonResponse
    {
        $data = $request->validate(['reason' => ['required','string','min:3','max:1000']]);
        return MobileApiResponse::success(DonationResource::make($this->service->cancel($this->user($request), $donation, $data['reason']))->resolve($request), 'Donation cancelled.');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
