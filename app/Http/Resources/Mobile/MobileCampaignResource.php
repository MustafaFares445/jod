<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Models\Post;
use App\Models\PostImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MobileCampaignResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $publisher = $this->publisher();
        $images = $this->images();

        $data = [
            'id' => (string) $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => (string) ($this->content ?? $this->summary ?? ''),
            'category' => $this->category,
            'status' => $this->status,
            'publisher' => $publisher,
            'images' => $images,
            'location' => $this->location,
            'goalAmount' => (float) $this->goal_amount,
            'raisedAmount' => (float) $this->raised_amount,
            'beneficiariesCount' => (int) $this->beneficiaries_count,
            'donorsCount' => (int) $this->donors_count,
            'applicantsCount' => (int) $this->applicants_count,
            'stats' => [
                'likes' => 0,
                'comments' => 0,
                'shares' => 0,
            ],
            'viewsCount' => 0,
            'reactionsCount' => 0,
            'commentsCount' => 0,
            'sharesCount' => 0,
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'submittedAt' => $this->submitted_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'closedAt' => $this->closed_at?->toIso8601String(),
            'closedReason' => $this->closed_reason,
            'reviewedBy' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->name),
            'rejectionReason' => $this->rejection_reason,
            'organizationName' => $this->organization?->name,
            'managerName' => $this->creator?->name,
        ];

        if (isset($publisher['phoneNumber'])) {
            $data['phoneNumber'] = $publisher['phoneNumber'];
        }

        if (isset($publisher['whatsappNumber'])) {
            $data['whatsappNumber'] = $publisher['whatsappNumber'];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function publisher(): array
    {
        $organization = $this->relationLoaded('organization') ? $this->organization : null;
        $manager = $this->relationLoaded('creator') ? $this->creator : null;

        $publisherId = $organization?->id ?? $manager?->id ?? $this->creator_id ?? 'campaign-'.$this->id;
        $name = $organization?->name ?? $manager?->name ?? 'JOD';
        $email = $organization?->email ?? $manager?->email;
        $phone = $organization?->phone ?? $manager?->phone;
        $city = $organization?->location ?? $manager?->city ?? $this->location;
        $bio = $organization?->description ?? $manager?->bio;

        $publisher = [
            'id' => (string) $publisherId,
            'name' => (string) $name,
            'username' => $this->username($email, (string) $name),
            'avatarUrl' => null,
            'verified' => $organization !== null
                ? $organization->verification_status === 'verified'
                : $manager?->email_verified_at !== null,
        ];

        if (filled($bio)) {
            $publisher['bio'] = $bio;
        }

        if (filled($city)) {
            $publisher['city'] = $city;
        }

        if (filled($phone)) {
            $publisher['phoneNumber'] = $phone;
            $publisher['whatsappNumber'] = $phone;
        }

        return $publisher;
    }

    /** @return list<string> */
    private function images(): array
    {
        $campaignImages = collect($this->images ?? [])
            ->map(static fn (string $path): string => Storage::disk('public')->url($path));

        if (! $this->relationLoaded('posts')) {
            return $campaignImages->take(10)->values()->all();
        }

        $postImages = $this->posts
            ->flatMap(static function (Post $post) {
                return $post->relationLoaded('images') ? $post->images : [];
            })
            ->map(static fn (PostImage $image): string => $image->publicUrl());

        return $campaignImages
            ->concat($postImages)
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    private function username(?string $email, string $name): string
    {
        if (filled($email)) {
            return Str::before((string) $email, '@');
        }

        $slug = Str::slug($name, '.');

        return $slug !== '' ? $slug : 'jod';
    }
}
