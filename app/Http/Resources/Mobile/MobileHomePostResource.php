<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Models\PostImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MobileHomePostResource extends JsonResource
{
    /**
     * Return the canonical post contract consumed by the mobile Home, Search,
     * Post Details, Saved Posts, and profile post surfaces.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $postType = $this->mobilePostType();
        $campaign = $this->relationLoaded('campaign') ? $this->campaign : null;
        $ctaType = $this->ctaType($postType, $campaign !== null);
        $ctaState = $this->ctaState($ctaType);
        $publisher = $this->publisher();
        $targetId = in_array($ctaType, ['apply', 'donate'], true)
            ? ($campaign?->id ? (string) $campaign->id : null)
            : (string) $this->id;

        $cta = [
            'type' => $ctaType,
            'label' => $this->ctaLabel($ctaType),
        ];

        if ($targetId !== null) {
            $cta['targetId'] = $targetId;
        }

        if ($ctaState !== null) {
            $cta['state'] = $ctaState;
        }

        $data = [
            'id' => (string) $this->id,
            'publisher' => $publisher,
            'postType' => $postType,
            'content' => (string) ($this->content ?? $this->summary ?? ''),
            'createdAt' => ($this->published_at ?? $this->created_at)?->toIso8601String(),
            'images' => $this->relationLoaded('images')
                ? $this->images->map(static fn (PostImage $image): string => $image->publicUrl())->values()->all()
                : [],
            'cta' => $cta,
            'stats' => [
                'likes' => (int) $this->reactions_count,
                'comments' => (int) $this->comments_count,
                'shares' => (int) $this->shares_count,
            ],
            'saved' => $this->relationLoaded('saves') && $this->saves->isNotEmpty(),

            // Keep a small compatibility tail while mobile callers migrate to the
            // canonical nested fields above.
            'status' => $this->status,
            'campaignId' => $campaign?->id ? (string) $campaign->id : null,
            'location' => $this->location,
        ];

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if (isset($publisher['phoneNumber'])) {
            $data['phoneNumber'] = $publisher['phoneNumber'];
        }

        if (isset($publisher['whatsappNumber'])) {
            $data['whatsappNumber'] = $publisher['whatsappNumber'];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function publisher(): array
    {
        $organization = $this->relationLoaded('organization') ? $this->organization : null;
        $author = $this->relationLoaded('author') ? $this->author : null;

        $publisherId = $organization?->id
            ?? $author?->id
            ?? $this->author_id
            ?? 'post-'.$this->id;
        $name = $organization?->name
            ?? $author?->name
            ?? $this->author_name
            ?? 'JOD';
        $email = $organization?->email ?? $author?->email;
        $phone = $organization?->phone ?? $author?->phone;
        $city = $organization?->location ?? $author?->city ?? $this->location;
        $bio = $organization?->description ?? $author?->bio;
        $username = $organization !== null
            ? $this->derivedUsername($email, (string) $name)
            : (filled($author?->username)
                ? (string) $author->username
                : $this->derivedUsername($email, (string) $name));

        $publisher = [
            'id' => (string) $publisherId,
            'name' => (string) $name,
            'username' => $username,
            'verified' => $organization !== null
                ? $organization->verification_status === 'verified'
                : $author?->email_verified_at !== null,
        ];

        if ($organization === null && $author !== null && ($avatarUrl = $author->avatarUrl()) !== null) {
            $publisher['avatarUrl'] = $avatarUrl;
        }

        if (filled($bio)) {
            $publisher['bio'] = $bio;
        }

        if (filled($city)) {
            $publisher['city'] = $city;
        }

        if (filled($phone)) {
            $publisher['phoneNumber'] = $phone;
        }

        return $publisher;
    }

    private function derivedUsername(?string $email, string $name): string
    {
        if (filled($email)) {
            return Str::before($email, '@');
        }

        $slug = Str::slug($name, '.');

        return $slug !== '' ? $slug : 'jod';
    }

    private function mobilePostType(): string
    {
        return match ($this->type) {
            'volunteer_opportunity',
            'donation_campaign',
            'help_request',
            'campaign_update',
            'awareness' => $this->type,
            default => 'awareness',
        };
    }

    private function ctaType(string $postType, bool $hasCampaign): string
    {
        return match ($postType) {
            'volunteer_opportunity' => $hasCampaign ? 'apply' : 'contact',
            'donation_campaign' => $hasCampaign ? 'donate' : 'contact',
            'help_request' => 'contact',
            'campaign_update' => 'details',
            default => 'none',
        };
    }

    private function ctaLabel(string $ctaType): string
    {
        return match ($ctaType) {
            'apply' => 'قدّم الآن',
            'donate' => 'تبرّع الآن',
            'contact' => 'تواصل',
            'details' => 'عرض التفاصيل',
            default => '',
        };
    }

    private function ctaState(string $ctaType): ?string
    {
        if (! in_array($ctaType, ['apply', 'donate'], true)) {
            return null;
        }

        $campaign = $this->relationLoaded('campaign') ? $this->campaign : null;

        if ($campaign === null || $campaign->status !== 'active') {
            return 'closed';
        }

        if (
            $ctaType === 'apply'
            && $this->relationLoaded('campaignApplications')
            && $this->campaignApplications->isNotEmpty()
        ) {
            return 'submitted';
        }

        return 'open';
    }
}
