<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $organization = $this->relationLoaded('organization') ? $this->organization : null;
        $campaignType = $this->campaignType();

        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'description' => (string) ($this->content ?? $this->summary ?? ''),
            'summary' => $this->summary,
            'city' => $this->location,
            'status' => $this->status,
            'statusTag' => $this->statusTag(),
            'category' => $this->category,
            'campaignType' => $campaignType,
            'publisherId' => (string) $this->organization_id,
            'orgName' => $organization?->name,
            'verified' => $organization?->verification_status === 'verified',
            'endDate' => $this->end_date?->toDateString(),
            'goalAmount' => (float) $this->goal_amount,
            'raisedAmount' => (float) $this->raised_amount,
            'date' => $this->start_date?->toDateString(),
            'time' => $this->eventTime(),
            'requiredVolunteers' => (int) $this->required_volunteers,
            'joinedVolunteers' => (int) ($this->joined_volunteers_count ?? 0),
            'applicantsCount' => (int) $this->applicants_count,
            'donorsCount' => (int) $this->donors_count,
        ];
    }

    private function campaignType(): string
    {
        if (! $this->relationLoaded('posts')) {
            return 'general';
        }

        if ($this->posts->contains('type', 'donation_campaign')) {
            return 'donation';
        }

        if ($this->posts->contains('type', 'volunteer_opportunity')) {
            return 'volunteering';
        }

        return 'general';
    }

    private function eventTime(): ?string
    {
        if (! filled($this->event_time)) {
            return null;
        }

        return substr((string) $this->event_time, 0, 5);
    }

    private function statusTag(): string
    {
        $goal = (float) $this->goal_amount;
        $raised = (float) $this->raised_amount;

        if ($this->status === 'closed' || ($goal > 0 && $raised >= $goal)) {
            return 'اكتملت';
        }

        if ($goal > 0 && ($raised / $goal) >= 0.8) {
            return 'اقتربت من الاكتمال';
        }

        if ($this->end_date === null) {
            return 'عاجلة';
        }

        $daysRemaining = (int) now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false);
        if ($daysRemaining <= 3) {
            return 'عاجلة';
        }

        return "باقي {$daysRemaining} أيام";
    }
}
