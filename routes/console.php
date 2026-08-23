<?php

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\Notification;
use App\Services\NotificationEventService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:campaign-closing-soon', function (NotificationEventService $notifications): void {
    Campaign::query()
        ->where('status', 'active')
        ->whereDate('end_date', now()->addDays(3)->toDateString())
        ->chunkById(100, function ($campaigns) use ($notifications): void {
            foreach ($campaigns as $campaign) {
                $referencePath = '/campaigns/'.$campaign->id;
                $organizationPath = '/org/campaigns/'.$campaign->id;
                $alreadySentToday = Notification::query()
                    ->where('event_type', NotificationEventType::CampaignClosingSoon->value)
                    ->whereIn('reference_path', [$referencePath, $organizationPath])
                    ->whereDate('created_at', now()->toDateString())
                    ->exists();

                if ($alreadySentToday) {
                    continue;
                }

                $body = "ستنتهي حملة {$campaign->title} خلال 3 أيام.";

                $notifications->notifyOrganization(
                    (string) $campaign->organization_id,
                    NotificationEventType::CampaignClosingSoon,
                    'الحملة تقترب من موعد الانتهاء',
                    $body,
                    'campaign',
                    'normal',
                    $campaign->title,
                    $organizationPath,
                );

                $notifications->notifyCampaignParticipants(
                    $campaign,
                    NotificationEventType::CampaignClosingSoon,
                    'الحملة تقترب من موعد الانتهاء',
                    $body,
                    'campaign',
                    'normal',
                    $campaign->title,
                    $referencePath,
                );
            }
        });
})->purpose('Send notifications for active campaigns ending in three days');

Schedule::command('notifications:campaign-closing-soon')
    ->dailyAt('09:00')
    ->withoutOverlapping();
