<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MobilePublisherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Organization) {
            return $this->organizationPublisher($this->resource);
        }

        /** @var User $user */
        $user = $this->resource;

        return $this->userPublisher($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationPublisher(Organization $organization): array
    {
        $data = [
            'id' => (string) $organization->id,
            'publisherType' => 'organization',
            'name' => (string) $organization->name,
            'username' => $this->username($organization->email, (string) $organization->name),
            'avatarUrl' => $organization->logoMedia?->publicUrl(),
            'verified' => $organization->verification_status === 'verified',
            'followersCount' => $this->followersCount('organization', (string) $organization->id),
            'isFollowing' => $this->isFollowing('organization', (string) $organization->id),
        ];

        if (filled($organization->description)) {
            $data['bio'] = $organization->description;
        }

        if (filled($organization->location)) {
            $data['city'] = $organization->location;
        }

        if (filled($organization->phone)) {
            $data['phoneNumber'] = $organization->phone;
            $data['whatsappNumber'] = $organization->phone;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPublisher(User $user): array
    {
        $data = [
            'id' => (string) $user->id,
            'publisherType' => 'user',
            'name' => (string) $user->name,
            'username' => $this->username($user->email, (string) $user->name),
            'avatarUrl' => $this->relationLoaded('avatarMedia') ? $this->avatarMedia?->publicUrl() : null,
            'verified' => $user->email_verified_at !== null,
            'followersCount' => $this->followersCount('user', (string) $user->id),
            'isFollowing' => $this->isFollowing('user', (string) $user->id),
        ];

        if (filled($user->bio)) {
            $data['bio'] = $user->bio;
        }

        if (filled($user->city)) {
            $data['city'] = $user->city;
        }

        if (filled($user->phone)) {
            $data['phoneNumber'] = $user->phone;
            $data['whatsappNumber'] = $user->phone;
        }

        return $data;
    }

    private function followersCount(string $type, string $id): int
    {
        if ($this->resource->getAttribute('followers_count') !== null) {
            return (int) $this->resource->getAttribute('followers_count');
        }

        return PublisherFollow::query()->where('target_type', $type)->where('target_id', $id)->count();
    }

    private function isFollowing(string $type, string $id): bool
    {
        if ($this->resource->getAttribute('is_following') !== null) {
            return (bool) $this->resource->getAttribute('is_following');
        }

        $viewer = request()->user('sanctum');
        if (! $viewer instanceof User) return false;

        return PublisherFollow::query()
            ->where('follower_user_id', $viewer->id)
            ->where('target_type', $type)
            ->where('target_id', $id)
            ->exists();
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
