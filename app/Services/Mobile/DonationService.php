<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DonationService
{
    /**
     * @param  array{perPage?: int, campaignId?: string}  $params
     */
    public function paginateForUser(User $user, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));

        return Donation::query()
            ->with('campaign.organization')
            ->where('created_by', $user->id)
            ->when(
                filled($params['campaignId'] ?? null),
                fn (Builder $query) => $query->where('campaign_id', $params['campaignId']),
            )
            ->orderByDesc('donated_at')
            ->paginate($perPage);
    }

    public function findForUser(User $user, string $donationId): ?Donation
    {
        return Donation::query()
            ->with('campaign.organization')
            ->where('created_by', $user->id)
            ->whereKey($donationId)
            ->first();
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
                'city' => $attributes['city'] ?? null,
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

            return $donation->load('campaign.organization');
        });
    }
}
