<?php

use App\Enums\HelpRequestStatus;
use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\Notification;
use App\Models\Post;
use App\Services\Mobile\BehavioralInterestDecayService;
use App\Services\NotificationEventService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:campaign-closing-soon', function (): void {
    $notifications = app(NotificationEventService::class);

    Campaign::query()
        ->where('status', 'active')
        ->whereDate('end_date', now()->addDays(3)->toDateString())
        ->chunkById(100, function ($campaigns) use ($notifications): void {
            foreach ($campaigns as $campaign) {
                $referencePath = '/campaigns/'.$campaign->id;
                $organizationPath = '/org/campaigns/'.$campaign->id;
                $body = "ستنتهي حملة {$campaign->title} خلال 3 أيام.";

                $organizationAlreadySent = Notification::query()
                    ->where('event_type', NotificationEventType::CampaignClosingSoon->value)
                    ->where('reference_path', $organizationPath)
                    ->whereDate('created_at', now()->toDateString())
                    ->exists();

                if (! $organizationAlreadySent) {
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
                }

                $participantsAlreadySent = Notification::query()
                    ->where('event_type', NotificationEventType::CampaignClosingSoon->value)
                    ->where('reference_path', $referencePath)
                    ->whereDate('created_at', now()->toDateString())
                    ->exists();

                if (! $participantsAlreadySent) {
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
            }
        });
})->purpose('Send notifications for active campaigns ending in three days');

Artisan::command('jod:expire-help-requests', function (): void {
    $terminalStatuses = [
        HelpRequestStatus::Fulfilled->value,
        HelpRequestStatus::PartiallyFulfilled->value,
        HelpRequestStatus::NotFulfilled->value,
        HelpRequestStatus::Expired->value,
    ];

    $updated = Post::query()
        ->where('type', 'help_request')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->where(function ($query) use ($terminalStatuses): void {
            $query->whereNull('help_status')->orWhereNotIn('help_status', $terminalStatuses);
        })
        ->update([
            'help_status' => HelpRequestStatus::Expired->value,
            'updated_at' => now(),
        ]);

    $this->info("Expired {$updated} help request(s).");
})->purpose('Mark elapsed help requests as expired');

Artisan::command('jod:decay-behavioral-interests', function (): void {
    $updated = app(BehavioralInterestDecayService::class)->decay();
    $this->info("Decayed {$updated} behavioral interest record(s).");
})->purpose('Decay stale behavioral recommendation interests');

Schedule::command('notifications:campaign-closing-soon')
    ->dailyAt('09:00')
    ->withoutOverlapping();

Schedule::command('jod:expire-help-requests')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('jod:decay-behavioral-interests')
    ->weekly()
    ->withoutOverlapping();
