<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\CampaignApplication;
use App\Models\Notification;
use App\Models\Post;

class CampaignApplicationObserver
{
    private const INACTIVE_STATUSES = ['rejected', 'withdrawn'];

    private const NOTIFIABLE_STATUSES = ['under_review', 'approved', 'accepted', 'rejected'];

    public function updated(CampaignApplication $application): void
    {
        if (! $application->wasChanged('applicant_status')) {
            return;
        }

        $this->syncCampaignCount($application);

        if ($application->source !== 'mobile_app'
            || blank($application->created_by)
            || ! in_array($application->applicant_status, self::NOTIFIABLE_STATUSES, true)) {
            return;
        }

        [$title, $body] = $this->message($application);
        $postId = $application->campaign_id === null
            ? null
            : Post::query()
                ->where('campaign_id', $application->campaign_id)
                ->where('type', 'volunteer_opportunity')
                ->where('status', 'published')
                ->latest('published_at')
                ->value('id');

        Notification::query()->create([
            'title' => $title,
            'body' => $body,
            'mailbox' => 'inbox',
            'status' => 'unread',
            'category' => 'applicant',
            'recipient_scope' => 'users',
            'priority' => 'normal',
            'reference_label' => 'تفاصيل النشاط',
            'reference_path' => $postId !== null
                ? '/posts/'.$postId
                : '/apply/'.($application->campaign_id ?? $application->id),
            'organization_id' => $application->organization_id,
            'recipient_id' => $application->created_by,
            'sent_at' => now(),
        ]);
    }

    private function syncCampaignCount(CampaignApplication $application): void
    {
        if ($application->campaign_id === null) {
            return;
        }

        $campaign = $application->campaign()->first();
        if ($campaign === null) {
            return;
        }

        $campaign->update([
            'applicants_count' => CampaignApplication::query()
                ->where('campaign_id', $campaign->id)
                ->whereNotIn('applicant_status', self::INACTIVE_STATUSES)
                ->count(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function message(CampaignApplication $application): array
    {
        $campaign = $application->campaign_title;

        return match ($application->applicant_status) {
            'accepted', 'approved' => [
                'قبول طلب التطوع',
                'تم قبول طلبك للمشاركة في '.$campaign.'.',
            ],
            'rejected' => [
                'تحديث على طلب التطوع',
                'تم رفض طلبك للمشاركة في '.$campaign.'.',
            ],
            default => [
                'طلب التطوع قيد المراجعة',
                'طلبك للمشاركة في '.$campaign.' قيد المراجعة الآن.',
            ],
        };
    }
}
