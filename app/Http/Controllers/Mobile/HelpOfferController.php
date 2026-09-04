<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Enums\HelpRequestStatus;
use App\Enums\PersonalizationEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\HelpOfferCancelRequest;
use App\Http\Requests\Mobile\HelpOfferHistoryRequest;
use App\Http\Requests\Mobile\HelpOfferReasonRequest;
use App\Http\Requests\Mobile\HelpOfferRequest;
use App\Http\Requests\Mobile\HelpRequestStatusRequest;
use App\Http\Resources\Mobile\HelpOfferResource;
use App\Models\User;
use App\Services\Mobile\HelpOfferService;
use App\Services\Mobile\InteractionTrackingService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpOfferController extends Controller
{
    public function __construct(
        private readonly HelpOfferService $service,
        private readonly InteractionTrackingService $interactions,
    ) {}

    public function store(HelpOfferRequest $request, string $post): JsonResponse
    {
        $user = $this->user($request);
        $offer = $this->service->create($user, $post, $request->validated());
        $offer->loadMissing('post.category');

        if ($offer->post !== null) {
            $this->interactions->recordPostAction(
                $user,
                PersonalizationEventType::HelpOffer,
                $offer->post,
                ['offerId' => (string) $offer->id],
            );
        }

        return MobileApiResponse::success(
            HelpOfferResource::make($offer)->resolve($request),
            'Help offer created successfully.',
        );
    }

    public function index(HelpOfferHistoryRequest $request): JsonResponse
    {
        $paginator = $this->service->paginateForUser($this->user($request), $request->validated());

        return MobileApiResponse::paginated(
            $paginator->through(fn ($offer) => HelpOfferResource::make($offer)->resolve($request)),
            'Help offers retrieved successfully.',
        );
    }

    public function show(Request $request, string $offer): JsonResponse
    {
        $model = $this->service->findForUser($this->user($request), $offer);
        if ($model === null) {
            return MobileApiResponse::error('not_found', 'The requested help offer could not be found.', null, 404);
        }

        return MobileApiResponse::success(HelpOfferResource::make($model)->resolve($request));
    }

    public function accept(Request $request, string $offer): JsonResponse
    {
        return $this->offerResponse($request, $this->service->accept($this->user($request), $offer), 'Help offer accepted.');
    }

    public function reject(HelpOfferReasonRequest $request, string $offer): JsonResponse
    {
        return $this->offerResponse(
            $request,
            $this->service->reject($this->user($request), $offer, $request->validated('reason')),
            'Help offer rejected.',
        );
    }

    public function contact(Request $request, string $offer): JsonResponse
    {
        return $this->offerResponse($request, $this->service->markContacting($this->user($request), $offer), 'Help offer marked as contacting.');
    }

    public function agree(Request $request, string $offer): JsonResponse
    {
        return $this->offerResponse($request, $this->service->markAgreed($this->user($request), $offer), 'Help offer marked as agreed.');
    }

    public function cancel(HelpOfferCancelRequest $request, string $offer): JsonResponse
    {
        return $this->offerResponse(
            $request,
            $this->service->cancel($this->user($request), $offer, (string) $request->validated('reason')),
            'Help offer cancelled.',
        );
    }

    public function confirmProvided(Request $request, string $offer): JsonResponse
    {
        return $this->offerResponse($request, $this->service->confirmProvided($this->user($request), $offer), 'Provided confirmation recorded.');
    }

    public function confirmReceived(Request $request, string $offer): JsonResponse
    {
        return $this->offerResponse($request, $this->service->confirmReceived($this->user($request), $offer), 'Received confirmation recorded.');
    }

    public function updatePostStatus(HelpRequestStatusRequest $request, string $post): JsonResponse
    {
        $model = $this->service->updateHelpStatus(
            $this->user($request),
            $post,
            HelpRequestStatus::from((string) $request->validated('status')),
        );

        return MobileApiResponse::success([
            'id' => (string) $model->id,
            'helpStatus' => $model->help_status?->value ?? $model->help_status,
        ], 'Help request status updated.');
    }

    private function offerResponse(Request $request, mixed $offer, string $message): JsonResponse
    {
        return MobileApiResponse::success(HelpOfferResource::make($offer)->resolve($request), $message);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
