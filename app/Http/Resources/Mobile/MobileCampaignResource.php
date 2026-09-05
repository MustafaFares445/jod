<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MobileCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $publisher = $this->publisher();
        $images = $this->images();
        $engagementPost = $this->engagementPost($request);
        $isLiked = $engagementPost?->relationLoaded('likes') === true && $engagementPost->likes->isNotEmpty();
        $likesCount = (int) ($engagementPost?->reactions_count ?? 0);

        $data = [
            'id' => (string) $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => (string) ($this->content ?? $this->summary ?? ''),
            'category' => $this->category,
            'audience' => $this->audience ?? 'general',
            'status' => $this->status,
            'publisher' => $publisher,
            'images' => $images,
            'location' => $this->location,
            'goalAmount' => (float) $this->goal_amount,
            'raisedAmount' => (float) $this->raised_amount,
            'beneficiariesCount' => (int) $this->beneficiaries_count,
            'donorsCount' => (int) $this->donors_count,
            'applicantsCount' => (int) $this->applicants_count,
            'stats' => ['likes' => $likesCount, 'comments' => 0, 'shares' => 0],
            'viewsCount' => 0,
            'reactionsCount' => $likesCount,
            'isLiked' => $isLiked,
            'engagementPostId' => $engagementPost?->id !== null ? (string) $engagementPost->id : null,
            'commentsCount' => 0,
            'sharesCount' => 0,
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'submittedAt' => $this->submitted_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'closedAt' => $this->closed_at?->toIso8601String(),
            'closedReason' => $this->closed_reason,
            'organizationName' => $this->organization?->name,
            'managerName' => $this->creator?->name,
        ];

        if (isset($publisher['phoneNumber'])) $data['phoneNumber'] = $publisher['phoneNumber'];
        if (isset($publisher['whatsappNumber'])) $data['whatsappNumber'] = $publisher['whatsappNumber'];
        return $data;
    }

    private function engagementPost(Request $request): ?Post
    {
        $post = $this->relationLoaded('posts')
            ? $this->posts->first()
            : $this->resource->posts()
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->first();

        $viewer = $request->user('sanctum');
        if ($post !== null && $viewer !== null && ! $post->relationLoaded('likes')) {
            $post->load(['likes' => static fn ($likes) => $likes->where('user_id', $viewer->id)]);
        }

        return $post;
    }

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
            'avatarUrl' => $organization?->logoMedia?->publicUrl(),
            'verified' => $organization !== null ? $organization->verification_status === 'verified' : $manager?->email_verified_at !== null,
        ];
        if (filled($bio)) $publisher['bio'] = $bio;
        if (filled($city)) $publisher['city'] = $city;
        if (filled($phone)) {
            $publisher['phoneNumber'] = $phone;
            $publisher['whatsappNumber'] = $phone;
        }
        return $publisher;
    }

    private function images(): array
    {
        $campaignImages = ($this->relationLoaded('imageMedia') ? $this->imageMedia : $this->resource->imageMedia()->get())
            ->map(static fn (Media $media): string => $media->publicUrl());
        if (! $this->relationLoaded('posts')) return $campaignImages->take(10)->values()->all();
        $postImages = $this->posts
            ->flatMap(static function (Post $post) { return $post->relationLoaded('images') ? $post->images : $post->images()->get(); })
            ->map(static fn (Media $media): string => $media->publicUrl());
        return $campaignImages->concat($postImages)->unique()->take(10)->values()->all();
    }

    private function username(?string $email, string $name): string
    {
        if (filled($email)) return Str::before((string) $email, '@');
        $slug = Str::slug($name, '.');
        return $slug !== '' ? $slug : 'jod';
    }
}
