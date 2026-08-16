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
            'name' => (string) $organization->name,
            'username' => $this->derivedUsername($organization->email, (string) $organization->name),
            'verified' => $organization->verification_status === 'verified',
            'stats' => $this->stats($organization),
        ];

        if (filled($organization->description)) {
            $data['bio'] = $organization->description;
        }

        if (filled($organization->location)) {
            $data['city'] = $organization->location;
        }

        if (filled($organization->phone)) {
            $data['phoneNumber'] = $organization->phone;
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
            'name' => (string) $user->name,
            'username' => filled($user->username)
                ? (string) $user->username
                : $this->derivedUsername($user->email, (string) $user->name),
            'verified' => $user->email_verified_at !== null,
            'stats' => $this->stats($user),
        ];

        if (($avatarUrl = $user->avatarUrl()) !== null) {
            $data['avatarUrl'] = $avatarUrl;
        }

        if (filled($user->bio)) {
            $data['bio'] = $user->bio;
        }

        if (filled($user->city)) {
            $data['city'] = $user->city;
        }

        if (filled($user->phone)) {
            $data['phoneNumber'] = $user->phone;
        }

        return $data;
    }

    /**
     * @return array{postsCount: int, likesCount: int, sharesCount: int}
     */
    private function stats(Organization|User $publisher): array
    {
        return [
            'postsCount' => (int) ($publisher->published_posts_count ?? 0),
            'likesCount' => (int) ($publisher->published_likes_count ?? 0),
            'sharesCount' => (int) ($publisher->published_shares_count ?? 0),
        ];
    }

    private function derivedUsername(?string $email, string $name): string
    {
        if (filled($email)) {
            return Str::before((string) $email, '@');
        }

        $slug = Str::slug($name, '.');

        return $slug !== '' ? $slug : 'jod';
    }
}
