<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DonationService
{
    public function __construct(private readonly NotificationEventService $notifications) {}

    /**
     * @param  array{perPage?: int, campaignId?: string, flow?: 'contributed'|'received'}  $params
     */
    public function paginateForUser(User $user, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $flow = (string) ($params['flow'] ?? 'contributed');

        $query = Donation::query()->with('campaign.organization');

        if ($flow === 'received') {
            if (! Gate::forUser($user)->allows('viewAny', Donation::class)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('organization_id', $user->organization_id);
            }
        } else {
            $query->where('created_by', $user->id);
        }

        return $query
            ->when(
                filled($params['campaignId'] ?? null),
                fn (Builder $builder) => $builder->where('campaign_id', $params['campaignId']),
            )
            ->orderByDesc('donated_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForUser(User $user, string $donationId): ?Donation
    {
        $donation = Donation::query()
            ->with('campaign.organization')
            ->whereKey($donationId)
            ->first();

        if ($donation === null) {
            return null;
        }

        if ((string) $donation->created_by === (string) $user->id) {
            return $donation;
        }

        return Gate::forUser($user)->allows('view', $donation) ? $donation : null;
    }

    /**
     * @param  array{amount: int|float|string, paymentMethod: string, phone?: string|null, city?: string|null}  $attributes
     */
    public function record(User $user, string $campaignId, array $attributes): Donation
    {
        return DB::transaction(function () use ($user, $campaignId, $attributes): Donation {
            $campaign = Campaign::query()
                ->whereKey($campaignId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($campaign === null) {
                throw ValidationException::withMessages([
                    'campaign' => ['The selected campaign is not available for donations.'],
                ]);
            }

            $amount = number_format((float) $attributes['amount'], 2, '.', '');
            $previousRaised = (float) $campaign->raised_amount;
            $hasPreviousDonation = Donation::query()
                ->where('campaign_id', $campaign->id)
                ->where('created_by', $user->id)
                ->exists();

            $donation = Donation::query()->create([
                'organization_id' => $campaign->organization_id,
                'campaign_id' => $campaign->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $attributes['phone'] ?? $user->phone,
                'campaign_title' => $campaign->title,
                'amount_or_type' => $amount,
                'donated_at' => now(),
                'city' => $attributes['city'] ?? $user->city,
                'source' => 'mobile_app',
                'payment_method' => $attributes['paymentMethod'],
                'campaign_ref' => $campaign->id,
                'assigned_to' => null,
                'internal_notes' => null,
                'created_by' => $user->id,
            ]);

            $campaign->increment('raised_amount', $amount);
            if (! $hasPreviousDonation) {
                $campaign->increment('donors_count');
            }
            $campaign->refresh();

            $formattedAmount = number_format((float) $amount, 2);
            $this->notifications->notifyUser(
                $user,
                NotificationEventType::DonationCompleted,
                'تم تسجيل تبرعك بنجاح',
                "شكراً لك. تم تسجيل تبرع بقيمة {$formattedAmount} لحملة {$campaign->title}.",
                'donation',
                'normal',
                $campaign->title,
                '/campaigns/'.$campaign->id,
                (string) $campaign->organization_id,
            );

            $this->notifications->notifyOrganization(
                (string) $campaign->organization_id,
                NotificationEventType::DonationReceived,
                'تبرع جديد للحملة',
                "تم استلام تبرع بقيمة {$formattedAmount} لحملة {$campaign->title}.",
                'donation',
                'high',
                $campaign->title,
                '/org/donations/'.$donation->id,
                (string) $user->id,
            );

            $goal = (float) $campaign->goal_amount;
            $raised = (float) $campaign->raised_amount;
            if ($goal > 0 && $previousRaised < $goal && $raised >= $goal) {
                $this->notifications->notifyOrganization(
                    (string) $campaign->organization_id,
                    NotificationEventType::CampaignGoalReached,
                    'الحملة وصلت إلى هدفها',
                    "وصلت حملة {$campaign->title} إلى هدف التبرعات المحدد.",
                    'campaign',
                    'high',
                    $campaign->title,
                    '/org/campaigns/'.$campaign->id,
                );
            }

            return $donation->load('campaign.organization');
        });
    }
}
