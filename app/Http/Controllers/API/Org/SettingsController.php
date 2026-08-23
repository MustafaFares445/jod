<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\BankAccountRequest;
use App\Http\Requests\Org\OrganizationProfileRequest;
use App\Http\Resources\MediaResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function profile(): JsonResponse
    {
        $org = $this->organization()->loadMissing('logoMedia');
        $this->authorize('viewSettings', $org);

        return response()->json(['data' => $this->profileData($org)]);
    }

    public function updateProfile(OrganizationProfileRequest $request): JsonResponse
    {
        $org = $this->organization();
        $this->authorize('updateSettings', $org);
        $data = $request->validated();

        $org->update([
            'name' => $data['companyName'] ?? $org->name,
            'owner_full_name' => $data['ownerName'] ?? $org->owner_full_name,
            'organization_number' => $data['organizationNumber'] ?? $org->organization_number,
            'registration_number' => $data['registrationNumber'] ?? $org->registration_number,
            'bank_account_number' => $data['bankAccountNumber'] ?? $org->bank_account_number,
            'email' => $data['companyEmail'] ?? $org->email,
            'phone' => $data['companyPhone'] ?? $org->phone,
            'owner_email' => $data['companyEmail'] ?? $org->owner_email,
            'owner_phone' => $data['companyPhone'] ?? $org->owner_phone,
            'location' => $data['location'] ?? $org->location,
            'website' => array_key_exists('website', $data) ? $data['website'] : $org->website,
        ]);

        return response()->json(['data' => $this->profileData($org->refresh()->load('logoMedia'))]);
    }

    public function bankAccount(): JsonResponse
    {
        $org = $this->organization();
        $this->authorize('viewSettings', $org);

        return response()->json([
            'data' => [
                'bankName' => $org->bank_name,
                'iban' => $org->iban,
            ],
        ]);
    }

    public function updateBankAccount(BankAccountRequest $request): JsonResponse
    {
        $org = $this->organization();
        $this->authorize('updateSettings', $org);

        $org->update([
            'bank_name' => $request->validated('bankName'),
            'iban' => $request->validated('iban'),
        ]);

        return response()->json([
            'data' => [
                'bankName' => $org->bank_name,
                'iban' => $org->iban,
            ],
        ]);
    }

    private function organization(): Organization
    {
        /** @var Organization|null $org */
        $org = auth()->user()?->organization;
        abort_if(! $org, 404, 'Organization not found');

        return $org;
    }

    /** @return array<string, mixed> */
    private function profileData(Organization $org): array
    {
        $logo = $org->relationLoaded('logoMedia') ? $org->logoMedia : null;

        return [
            'id' => $org->id,
            'companyName' => $org->name,
            'ownerName' => $org->owner_full_name,
            'organizationNumber' => $org->organization_number,
            'registrationNumber' => $org->registration_number,
            'bankAccountNumber' => $org->bank_account_number,
            'companyEmail' => $org->email,
            'companyPhone' => $org->phone,
            'location' => $org->location,
            'website' => $org->website,
            'image' => $logo?->publicUrl(),
            'logo' => $logo ? MediaResource::make($logo)->resolve() : null,
        ];
    }
}
