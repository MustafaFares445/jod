<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\PersonalCampaignRequest;
use App\Http\Resources\Mobile\PersonalCampaignResource;
use App\Models\Campaign;
use App\Services\Mobile\PersonalCampaignService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalCampaignController extends Controller
{
    public function __construct(private readonly PersonalCampaignService $service) {}

    public function index(Request $request): JsonResponse
    {
        $params = validator($request->query(), ['page'=>['sometimes','integer','min:1'],'perPage'=>['sometimes','integer','min:1','max:100'],'status'=>['sometimes','string','in:pending,active,rejected,suspended,closed']])->validate();
        $paginator = $this->service->paginateOwned($request->user(), $params);
        return MobileApiResponse::paginated($paginator->through(fn ($campaign) => PersonalCampaignResource::make($campaign)->resolve($request)), 'Personal campaigns retrieved successfully.');
    }

    public function store(PersonalCampaignRequest $request): JsonResponse
    {
        $campaign = $this->service->create($request->user(), $request->validated(), $request->file('images', []));
        return MobileApiResponse::success(PersonalCampaignResource::make($campaign)->resolve($request), 'Personal campaign submitted for review.');
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $this->service->ensureOwner($campaign, $request->user());
        return MobileApiResponse::success(PersonalCampaignResource::make($this->service->load($campaign))->resolve($request));
    }

    public function update(PersonalCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        $campaign = $this->service->updateOwned($campaign, $request->user(), $request->validated(), $request->file('images', []));
        return MobileApiResponse::success(PersonalCampaignResource::make($campaign)->resolve($request), 'Personal campaign updated and submitted for review.');
    }

    public function close(Request $request, Campaign $campaign): JsonResponse
    {
        $data = $request->validate(['reason'=>['required','string','min:3','max:1000']]);
        $campaign = $this->service->closeOwned($campaign, $request->user(), $data['reason']);
        return MobileApiResponse::success(PersonalCampaignResource::make($campaign)->resolve($request), 'Personal campaign closed.');
    }
}
