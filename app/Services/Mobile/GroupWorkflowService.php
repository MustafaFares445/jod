<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\NotificationEventType;
use App\Models\CampaignApplication;
use App\Models\Donation;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupWorkflowService
{
    public function __construct(
        private readonly DonationService $donations,
        private readonly NotificationEventService $notifications,
    ) {}

    public function applications(Group $group, User $actor, array $params): LengthAwarePaginator
    {
        $this->ensureManager($group, $actor);
        return CampaignApplication::query()
            ->with(['creator.avatarMedia', 'campaign'])
            ->where('group_id', $group->id)
            ->when(filled($params['status'] ?? null), fn (Builder $query) => $query->where('applicant_status', $params['status']))
            ->orderByDesc('applied_at')
            ->paginate(max(1, min((int) ($params['perPage'] ?? 20), 100)));
    }

    public function donations(Group $group, User $actor, array $params): LengthAwarePaginator
    {
        $this->ensureManager($group, $actor);
        return Donation::query()
            ->with(['creator.avatarMedia', 'campaign'])
            ->where('group_id', $group->id)
            ->when(filled($params['status'] ?? null), fn (Builder $query) => $query->where('status', $params['status']))
            ->orderByDesc('created_at')
            ->paginate(max(1, min((int) ($params['perPage'] ?? 20), 100)));
    }

    public function acceptApplication(Group $group, User $actor, CampaignApplication $application): CampaignApplication
    {
        return $this->applicationTransition($group, $actor, $application, ['pending', 'under_review'], 'accepted', NotificationEventType::ApplicationAccepted, 'تم قبول طلب التطوع', 'وافق مدير الفريق على طلب تطوعك.');
    }

    public function contactApplication(Group $group, User $actor, CampaignApplication $application): CampaignApplication
    {
        return $this->applicationTransition($group, $actor, $application, ['accepted', 'approved'], 'contacting', NotificationEventType::ApplicationContactStarted, 'بدأ التواصل بخصوص التطوع', 'بدأ فريق التطوع التواصل معك لتنسيق مشاركتك.');
    }

    public function completeApplication(Group $group, User $actor, CampaignApplication $application): CampaignApplication
    {
        return $this->applicationTransition($group, $actor, $application, ['contacting'], 'completed', NotificationEventType::ApplicationCompleted, 'اكتملت مشاركتك التطوعية', 'أكد مدير الفريق اكتمال مشاركتك التطوعية.');
    }

    public function rejectApplication(Group $group, User $actor, CampaignApplication $application): CampaignApplication
    {
        return $this->applicationTransition($group, $actor, $application, ['pending', 'under_review', 'accepted', 'approved'], 'rejected', NotificationEventType::ApplicationRejected, 'تم رفض طلب التطوع', 'لم يتم قبول طلب تطوعك في الفريق.');
    }

    public function acceptDonation(Group $group, User $actor, Donation $donation): Donation { $this->ensureDonation($group, $actor, $donation); return $this->donations->accept($actor, (string) $donation->id); }
    public function contactDonation(Group $group, User $actor, Donation $donation): Donation { $this->ensureDonation($group, $actor, $donation); return $this->donations->markContacting($actor, (string) $donation->id); }
    public function agreeDonation(Group $group, User $actor, Donation $donation): Donation { $this->ensureDonation($group, $actor, $donation); return $this->donations->markAgreed($actor, (string) $donation->id); }
    public function completeDonation(Group $group, User $actor, Donation $donation, float $amount): Donation { $this->ensureDonation($group, $actor, $donation); return $this->donations->complete($actor, (string) $donation->id, $amount); }
    public function cancelDonation(Group $group, User $actor, Donation $donation, string $reason): Donation { $this->ensureDonation($group, $actor, $donation); return $this->donations->cancel($actor, (string) $donation->id, $reason); }

    /** @param list<string> $from */
    private function applicationTransition(Group $group, User $actor, CampaignApplication $application, array $from, string $to, NotificationEventType $event, string $title, string $body): CampaignApplication
    {
        $this->ensureManager($group, $actor);
        if ((string) $application->group_id !== (string) $group->id) abort(404);
        return DB::transaction(function () use ($application, $from, $to, $event, $title, $body, $actor, $group): CampaignApplication {
            $locked = CampaignApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($locked->applicant_status === $to) return $locked->load(['creator', 'campaign']);
            if (! in_array((string) $locked->applicant_status, $from, true)) throw ValidationException::withMessages(['status' => ["Application cannot transition from {$locked->applicant_status} to {$to}."]]);
            $locked->update(['applicant_status' => $to]);
            if (filled($locked->created_by)) {
                $this->notifications->notifyUser((string) $locked->created_by, $event, $title, $body, 'applicant', 'high', $locked->campaign_title, '/applications/'.$locked->id, null, (string) $actor->id);
            }
            return $locked->refresh()->load(['creator', 'campaign']);
        });
    }

    private function ensureDonation(Group $group, User $actor, Donation $donation): void
    {
        $this->ensureManager($group, $actor);
        if ((string) $donation->group_id !== (string) $group->id) abort(404);
    }

    private function ensureManager(Group $group, User $actor): void
    {
        $allowed = GroupMember::query()->where('group_id', $group->id)->where('user_id', $actor->id)->where('status', 'active')->whereIn('role', ['owner', 'admin'])->exists();
        if (! $allowed) abort(403, 'Only group managers can manage this workflow.');
    }
}
