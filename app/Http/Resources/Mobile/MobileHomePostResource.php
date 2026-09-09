<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Enums\HelpOfferStatus;
use App\Enums\HelpRequestStatus;
use App\Models\HelpOffer;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class MobileHomePostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $postType = $this->mobilePostType();
        $viewerId = $request->user('sanctum')?->id;
        $ctaType = $viewerId !== null && (string) $viewerId === (string) $this->author_id
            ? 'none'
            : $this->ctaType($postType);
        $ctaState = $this->ctaState($ctaType);
        $publisher = $this->publisher();
        $campaign = $this->relationLoaded('campaign') ? $this->campaign : null;
        $category = $this->relationLoaded('category') ? $this->category : null;
        $targetId = match ($ctaType) {
            'apply' => (string) $this->id,
            'donate' => $campaign?->id ? (string) $campaign->id : null,
            default => (string) $this->id,
        };
        $cta = ['type' => $ctaType, 'label' => $this->ctaLabel($ctaType)];
        if ($targetId !== null) $cta['targetId'] = $targetId;
        if ($ctaState !== null) $cta['state'] = $ctaState;

        $isLiked = $this->relationLoaded('likes') && $this->likes->isNotEmpty();
        $isSaved = $this->relationLoaded('saves') && $this->saves->isNotEmpty();
        $images = $this->relationLoaded('images') ? $this->images : $this->resource->images()->get();
        $videos = $this->relationLoaded('videos') ? $this->videos : $this->resource->videos()->get();
        $campaignImages = $campaign?->relationLoaded('imageMedia') === true ? $campaign->imageMedia : collect();
        $displayImages = $images->isNotEmpty() ? $images : $campaignImages;

        $data = [
            'id' => (string) $this->id,
            'publisher' => $publisher,
            'postType' => $postType,
            'audience' => $this->audience ?? 'general',
            'content' => (string) ($this->content ?? $this->summary ?? ''),
            'createdAt' => ($this->published_at ?? $this->created_at)?->toIso8601String(),
            'images' => $displayImages->map(static fn (Media $image): string => $image->publicUrl())->values()->all(),
            'videos' => $videos->map(static fn (Media $video): string => $video->publicUrl())->values()->all(),
            'cta' => $cta,
            'stats' => ['likes' => (int) $this->reactions_count, 'comments' => 0, 'shares' => 0],
            'viewsCount' => (int) $this->views_count,
            'reactionsCount' => (int) $this->reactions_count,
            'commentsCount' => 0,
            'sharesCount' => 0,
            'isLiked' => $isLiked,
            'isSaved' => $isSaved,
            'saved' => $isSaved,
            'status' => $this->status,
            'campaignId' => $campaign?->id ? (string) $campaign->id : null,
            'location' => $this->location,
            'category' => $category ? [
                'id' => (string) $category->id,
                'name' => (string) $category->name,
            ] : null,
        ];

        if ($postType === 'help_request') $data = [...$data, ...$this->helpRequestState($request)];
        if ($this->title !== null) $data['title'] = $this->title;
        if (isset($publisher['phoneNumber'])) $data['phoneNumber'] = $publisher['phoneNumber'];
        if (isset($publisher['whatsappNumber'])) $data['whatsappNumber'] = $publisher['whatsappNumber'];
        return $data;
    }

    private function helpRequestState(Request $request): array
    {
        $viewer = $request->user('sanctum');
        $viewerId = $viewer?->id;
        $helpStatus = $this->help_status?->value ?? $this->help_status ?? HelpRequestStatus::Open->value;
        $helpStatusEnum = HelpRequestStatus::tryFrom((string) $helpStatus);
        $isExpired = $this->expires_at !== null && $this->expires_at->isPast();
        if ($isExpired && ($helpStatusEnum === null || ! $helpStatusEnum->isTerminal())) {
            $helpStatus = HelpRequestStatus::Expired->value;
            $helpStatusEnum = HelpRequestStatus::Expired;
        }

        $activeStatuses = [HelpOfferStatus::Pending->value, HelpOfferStatus::Accepted->value, HelpOfferStatus::Contacting->value, HelpOfferStatus::Agreed->value];
        $activeOffersCount = $this->helpOffers()->whereIn('status', $activeStatuses)->count();
        $myOffer = null;
        if ($viewerId !== null) {
            $offer = $this->helpOffers()->where('helper_user_id', $viewerId)->whereIn('status', $activeStatuses)->latest('created_at')->first();
            if ($offer !== null) $myOffer = ['id' => (string) $offer->id, 'status' => $offer->status?->value ?? (string) $offer->status];
        }

        $hasFinalAgreement = filled($this->selected_help_offer_id);
        $policyAllowsOffer = $viewer !== null
            && filled($this->author_id)
            && Gate::forUser($viewer)->allows('create', [HelpOffer::class, $this->resource]);

        $availability = match (true) {
            $helpStatusEnum === HelpRequestStatus::Fulfilled => 'fulfilled',
            $helpStatusEnum === HelpRequestStatus::PartiallyFulfilled => 'partially_fulfilled',
            $helpStatusEnum === HelpRequestStatus::NotFulfilled => 'not_fulfilled',
            $helpStatusEnum === HelpRequestStatus::Expired || $isExpired => 'expired',
            $myOffer !== null => 'existing_offer',
            $hasFinalAgreement => 'final_agreement',
            $viewer === null => 'login_required',
            ! $policyAllowsOffer => 'not_eligible',
            default => 'available',
        };

        return [
            'helpStatus' => $helpStatus,
            'hasFinalAgreement' => $hasFinalAgreement,
            'canOfferHelp' => $availability === 'available',
            'helpOfferAvailability' => $availability,
            'activeOffersCount' => $activeOffersCount,
            'myOffer' => $myOffer,
        ];
    }

    private function publisher(): array
    {
        $organization = $this->relationLoaded('organization') ? $this->organization : null;
        $author = $this->relationLoaded('author') ? $this->author : null;
        $publisherId = $organization?->id ?? $author?->id ?? $this->author_id ?? 'post-'.$this->id;
        $name = $organization?->name ?? $author?->name ?? $this->author_name ?? 'JOD';
        $email = $organization?->email ?? $author?->email;
        $phone = $organization?->phone ?? $author?->phone;
        $city = $organization?->location ?? $author?->city ?? $this->location;
        $bio = $organization?->description ?? $author?->bio;
        $publisher = [
            'id' => (string) $publisherId,
            'publisherType' => $organization !== null ? 'organization' : 'user',
            'name' => (string) $name,
            'username' => $this->username($email, (string) $name),
            'avatarUrl' => $organization?->logoMedia?->publicUrl() ?? $author?->avatarMedia?->publicUrl(),
            'verified' => $organization !== null ? $organization->verification_status === 'verified' : $author?->email_verified_at !== null,
        ];
        if (filled($bio)) $publisher['bio'] = $bio;
        if (filled($city)) $publisher['city'] = $city;
        if (filled($phone)) {
            $publisher['phoneNumber'] = $phone;
            $publisher['whatsappNumber'] = $phone;
        }
        return $publisher;
    }

    private function username(?string $email, string $name): string
    {
        if (filled($email)) return Str::before($email, '@');
        $slug = Str::slug($name, '.');
        return $slug !== '' ? $slug : 'jod';
    }

    private function mobilePostType(): string
    {
        return match ($this->type) {
            'volunteer_opportunity', 'donation_campaign', 'help_request', 'service_offer', 'campaign_update', 'awareness' => $this->type,
            default => 'awareness',
        };
    }

    private function ctaType(string $postType): string
    {
        return match ($postType) { 'volunteer_opportunity' => 'apply', 'donation_campaign' => 'donate', 'help_request', 'service_offer' => 'contact', 'campaign_update' => 'details', default => 'none' };
    }

    private function ctaLabel(string $ctaType): string
    {
        return match ($ctaType) { 'apply' => 'قدّم الآن', 'donate' => 'تبرّع الآن', 'contact' => $this->type === 'service_offer' ? 'تواصل' : 'تقديم مساعدة', 'details' => 'عرض التفاصيل', default => '' };
    }

    private function workflowCtaState(mixed $status): string
    {
        return match ((string) $status) {
            'pending', 'under_review' => 'submitted',
            'accepted', 'approved' => 'accepted',
            'contacting' => 'contacting',
            'agreed' => 'agreed',
            'completed' => 'completed',
            default => 'open',
        };
    }

    private function ctaState(string $ctaType): ?string
    {
        if ($ctaType === 'contact' && $this->type === 'help_request') {
            $helpStatus = HelpRequestStatus::tryFrom((string) ($this->help_status?->value ?? $this->help_status ?? HelpRequestStatus::Open->value));
            $isExpired = $this->expires_at !== null && $this->expires_at->isPast();
            return $helpStatus?->isTerminal() === true || $isExpired || filled($this->selected_help_offer_id) ? 'closed' : 'open';
        }
        if (! in_array($ctaType, ['apply', 'donate'], true)) return null;

        $campaign = $this->relationLoaded('campaign') ? $this->campaign : null;
        if ($ctaType === 'donate') {
            if ($campaign === null || $campaign->status !== 'active') return 'closed';
            $donation = $this->relationLoaded('campaignDonations') ? $this->campaignDonations->first() : null;
            return $this->workflowCtaState($donation?->status?->value ?? $donation?->status);
        }

        if ($campaign !== null) {
            if ($campaign->status !== 'active') return 'closed';
            $application = $this->relationLoaded('campaignApplications') ? $this->campaignApplications->first() : null;
            return $this->workflowCtaState($application?->applicant_status);
        }

        if (! filled($this->organization_id)) return 'closed';
        $application = $this->relationLoaded('volunteerApplications') ? $this->volunteerApplications->first() : null;
        return $this->workflowCtaState($application?->applicant_status);
    }
}
