<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;

class MobileProfilePostResource extends MobileHomePostResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['profileStatus'] = match ($this->status) {
            'published' => 'posted',
            'rejected' => 'unposted',
            'archived' => 'archived',
            default => null,
        };
        $data['rejectionReason'] = $this->rejection_reason;

        // The current profile edit flow reads city from publisher.city when it
        // opens the create/edit screen. For an owned post this needs to reflect
        // the post location, not the user's general profile city.
        if (filled($this->location)) {
            $data['publisher']['city'] = $this->location;
        }

        return $data;
    }
}
