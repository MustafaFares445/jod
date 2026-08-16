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
            'username' => $this->username($organization->email, (string) $organization->name),
            'verified' => $organization->verification_status === 'verified',
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
            'username' => $this->username($user->email, (string) $user->name),
            'verified' => $user->email_verified_at !== null,
        ];

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

    private function username(?string $email, string $name): string
    {
        if (filled($email)) {
            return Str::before((string) $email, '@');
        }

        $slug = Str::slug($name, '.');

        return $slug !== '' ? $slug : 'jod';
    }
}
