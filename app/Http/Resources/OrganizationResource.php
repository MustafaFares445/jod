<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'verificationStatus' => $this->verification_status,
            'status' => $this->status,
            'campaignsCount' => (int) $this->campaigns_count,
            'postsCount' => (int) $this->posts_count,
            'activeVolunteersCount' => (int) $this->active_volunteers_count,
            'activityScore' => (float) $this->activity_score,
            'createdAt' => $this->created_at?->toIso8601String(),
            'lastActiveAt' => $this->last_active_at?->toIso8601String(),
            'organizationType' => $this->organization_type,
            'registrationNumber' => $this->registration_number,
            'establishmentDate' => $this->establishment_date?->toDateString(),
            'shortAddress' => $this->short_address,
            'description' => $this->description,
            'licenseDocumentName' => $this->license_document_name,
            'delegationDocumentName' => $this->delegation_document_name,
            'ownerFullName' => $this->owner_full_name,
            'ownerEmail' => $this->owner_email,
            'ownerPhone' => $this->owner_phone,
            'website' => $this->website,
            'socialMedia' => $this->social_media ?? [],
            'bankName' => $this->bank_name,
            'iban' => $this->iban,
            'acceptedAt' => $this->accepted_at?->toIso8601String(),
        ];
    }
}
