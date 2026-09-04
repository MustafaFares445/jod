<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Resources\Org\HelpOfferResource;
use App\Models\HelpOffer;
use App\Models\Post;
use App\Services\Mobile\HelpOfferService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class HelpOfferController extends Controller
{
    public function __construct(private readonly HelpOfferService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAnyOrganization', Post::class);
        $organizationId = $this->organizationId();
        $perPage = max(1, min((int) $request->input('perPage', 20), 100));
        $offers = HelpOffer::query()->with(['post', 'helper', 'postOwner'])
            ->whereHas('post', fn (Builder $q) => $q->where('organization_id', $organizationId)->where('type', 'help_request'))
            ->when($request->filled('postId'), fn (Builder $q) => $q->where('post_id', $request->input('postId')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')->paginate($perPage);
        return HelpOfferResource::collection($offers);
    }

    public function show(HelpOffer $offer): HelpOfferResource
    {
        $offer->loadMissing(['post', 'helper', 'postOwner']);
        $this->assertOwns($offer);
        return HelpOfferResource::make($offer);
    }

    public function accept(Request $request, HelpOffer $offer): HelpOfferResource
    {
        $this->assertOwns($offer);
        return HelpOfferResource::make($this->service->accept($request->user(), (string) $offer->id));
    }

    public function reject(Request $request, HelpOffer $offer): HelpOfferResource
    {
        $this->assertOwns($offer);
        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        return HelpOfferResource::make($this->service->reject($request->user(), (string) $offer->id, $request->input('reason')));
    }

    public function contact(Request $request, HelpOffer $offer): HelpOfferResource
    {
        $this->assertOwns($offer);
        return HelpOfferResource::make($this->service->markContacting($request->user(), (string) $offer->id));
    }

    public function agree(Request $request, HelpOffer $offer): HelpOfferResource
    {
        $this->assertOwns($offer);
        return HelpOfferResource::make($this->service->markAgreed($request->user(), (string) $offer->id));
    }

    public function confirmReceived(Request $request, HelpOffer $offer): HelpOfferResource
    {
        $this->assertOwns($offer);
        return HelpOfferResource::make($this->service->confirmReceived($request->user(), (string) $offer->id));
    }

    private function assertOwns(HelpOffer $offer): void
    {
        $offer->loadMissing('post');
        abort_unless($offer->post && (string) $offer->post->organization_id === $this->organizationId(), 404);
        $this->authorize('updateOrganization', $offer->post);
    }

    private function organizationId(): string
    {
        $id = (string) auth()->user()?->organization_id;
        if ($id === '') throw ValidationException::withMessages(['organizationId' => ['Authenticated user is not linked to an organization.']]);
        return $id;
    }
}
