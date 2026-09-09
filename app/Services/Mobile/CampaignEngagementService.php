<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\CampaignLike;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Support\Facades\DB;

class CampaignEngagementService
{
    public function __construct(private readonly NotificationEventService $notifications) {}

    /** @return array{campaignId: string, isLiked: bool, likesCount: int} */
    public function like(User $user, string $campaignId): array
    {
        return DB::transaction(function () use ($user, $campaignId): array {
            $campaign = $this->findPublicCampaignForUpdate($campaignId);
            $like = CampaignLike::query()->firstOrCreate([
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
            ]);

            if ($like->wasRecentlyCreated && filled($campaign->organization_id)) {
                $this->notifications->notifyOrganization(
                    (string) $campaign->organization_id,
                    NotificationEventType::CampaignLiked,
                    'إعجاب جديد على حملة',
                    "أعجب {$user->name} بحملة {$campaign->title}.",
                    'campaign',
                    'normal',
                    $campaign->title,
                    '/org/campaigns/'.$campaign->id,
                    (string) $user->id,
                    (string) $user->id,
                );
            }

            return $this->state($campaign, true);
        });
    }

    /** @return array{campaignId: string, isLiked: bool, likesCount: int} */
    public function unlike(User $user, string $campaignId): array
    {
        return DB::transaction(function () use ($user, $campaignId): array {
            $campaign = $this->findPublicCampaignForUpdate($campaignId);
            CampaignLike::query()
                ->where('user_id', $user->id)
                ->where('campaign_id', $campaign->id)
                ->delete();

            return $this->state($campaign, false);
        });
    }

    private function findPublicCampaignForUpdate(string $campaignId): Campaign
    {
        return Campaign::query()
            ->whereKey($campaignId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return array{campaignId: string, isLiked: bool, likesCount: int} */
    private function state(Campaign $campaign, bool $isLiked): array
    {
        return [
            'campaignId' => (string) $campaign->id,
            'isLiked' => $isLiked,
            'likesCount' => CampaignLike::query()->where('campaign_id', $campaign->id)->count(),
        ];
    }
}
