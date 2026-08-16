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
        $ctaType = $this->ctaType($postType);
        $ctaState = $this->ctaState($ctaType);
        $publisher = $this->publisher();
        $campaign = $this->relationLoaded('campaign') ? $this->campaign : null;

        $cta = [
            'type' => $ctaType,
            'label' => $this->ctaLabel($ctaType),
            'targetId' => in_array($ctaType, ['apply', 'donate'], true)
                ? ($campaign?->id ? (string) $campaign->id : null)
                : (string) $this->id,
        ];

        if ($ctaState !== null) {
            $cta['state'] = $ctaState;
        }

        return [
            'id' => (string) $this->id,
            'publisher' => $publisher,
            'postType' => $postType,
            'title' => $this->title,
            'content' => (string) ($this->content ?? $this->summary ?? ''),
            'createdAt' => ($this->published_at ?? $this->created_at)?->toIso8601String(),
            'images' => $this->relationLoaded('images')
                ? $this->images->map(static fn (PostImage $image): string => $image->publicUrl())->values()->all()
                : [],
            'cta' => $cta,
            'phoneNumber' => $publisher['phoneNumber'],
            'whatsappNumber' => $publisher['whatsappNumber'],
            'stats' => [
                'likes' => (int) $this->reactions_count,
                'comments' => 0,
                'shares' => 0,
            ],
            'saved' => $this->relationLoaded('saves') && $this->saves->isNotEmpty(),

            // Keep a small compatibility tail while mobile callers migrate to the
            // canonical nested fields above.
            'status' => $this->status,
            'campaignId' => $campaign?->id ? (string) $campaign->id : null,
            'location' => $this->location,
        ];
    }

    /**
     * @return array{id: string, name: string, username: string, avatarUrl: null, bio: string|null, city: string|null, verified: bool, phoneNumber: string|null, whatsappNumber: null}
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
        $city = $organization?->location ?? $this->location;

        return [
            'id' => (string) $publisherId,
            'name' => (string) $name,
            'username' => $this->username($email, (string) $name),
            'avatarUrl' => null,
            'bio' => $organization?->description,
            'city' => $city,
            'verified' => $organization !== null
                ? $organization->verification_status === 'verified'
                : $author?->email_verified_at !== null,
            'phoneNumber' => $phone,
            'whatsappNumber' => null,
        ];
    }

    private function username(?string $email, string $name): string
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

    private function ctaType(string $postType): string
    {
        return match ($postType) {
            'volunteer_opportunity' => 'apply',
            'donation_campaign' => 'donate',
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

        if ($campaign !== null && $campaign->status !== 'active') {
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
