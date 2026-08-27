<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Enums\HelpOfferStatus;
use App\Enums\HelpRequestStatus;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
        $targetId = in_array($ctaType, ['apply', 'donate'], true) ? ($campaign?->id ? (string) $campaign->id : null) : (string) $this->id;
        $cta = ['type' => $ctaType, 'label' => $this->ctaLabel($ctaType)];
        if ($targetId !== null) $cta['targetId'] = $targetId;
        if ($ctaState !== null) $cta['state'] = $ctaState;

        $isLiked = $this->relationLoaded('likes') && $this->likes->isNotEmpty();
        $isSaved = $this->relationLoaded('saves') && $this->saves->isNotEmpty();
        $images = $this->relationLoaded('images') ? $this->images : $this->resource->images()->get();
        $videos = $this->relationLoaded('videos') ? $this->videos : $this->resource->videos()->get();

        $data = [
            'id' => (string) $this->id,
            'publisher' => $publisher,
            'postType' => $postType,
            'audience' => $this->audience ?? 'general',
            'content' => (string) ($this->content ?? $this->summary ?? ''),
            'createdAt' => ($this->published_at ?? $this->created_at)?->toIso8601String(),
            'images' => $images->map(static fn (Media $image): string => $image->publicUrl())->values()->all(),
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
            'status' => $this->status === 'approved' ? 'published' : $this->status,
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
        $viewerId = $request->user('sanctum')?->id;
        $helpStatus = $this->help_status?->value ?? $this->help_status ?? HelpRequestStatus::Open->value;
        $activeStatuses = [HelpOfferStatus::Pending->value, HelpOfferStatus::Accepted->value, HelpOfferStatus::Contacting->value, HelpOfferStatus::Agreed->value];
        $activeOffersCount = $this->helpOffers()->whereIn('status', $activeStatuses)->count();
        $myOffer = null;
        if ($viewerId !== null) {
            $offer = $this->helpOffers()->where('helper_user_id', $viewerId)->whereIn('status', $activeStatuses)->latest('created_at')->first();
            if ($offer !== null) $myOffer = ['id' => (string) $offer->id, 'status' => $offer->status?->value ?? (string) $offer->status];
        }
        return [
            'helpStatus' => $helpStatus,
            'canOfferHelp' => $viewerId !== null && (string) $viewerId !== (string) $this->author_id && $helpStatus !== HelpRequestStatus::Fulfilled->value && $myOffer === null,
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

    private function ctaState(string $ctaType): ?string
    {
        if ($ctaType === 'contact' && $this->type === 'help_request') {
            return ($this->help_status?->value ?? $this->help_status) === HelpRequestStatus::Fulfilled->value ? 'closed' : 'open';
        }
        if (! in_array($ctaType, ['apply', 'donate'], true)) return null;
        $campaign = $this->relationLoaded('campaign') ? $this->campaign : null;
        if ($campaign === null || $campaign->status !== 'active') return 'closed';
        if ($ctaType === 'apply' && $this->relationLoaded('campaignApplications') && $this->campaignApplications->isNotEmpty()) return 'submitted';
        return 'open';
    }
}
