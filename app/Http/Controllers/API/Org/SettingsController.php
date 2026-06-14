<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\BankAccountRequest;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function profile(): JsonResponse
    {
        /** @var Organization|null $org */
        $org = auth()->user()->organization;
        if (! $org) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $this->authorize('viewSettings', $org);

        return response()->json([
            'data' => [
                'id' => $org?->id,
                'name' => $org?->name,
                'email' => $org?->email,
                'phone' => $org?->phone,
            ],
        ]);
    }

    public function updateProfile(): JsonResponse
    {
        /** @var Organization|null $org */
        $org = auth()->user()->organization;
        if (! $org) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $this->authorize('updateSettings', $org);

        $data = request()->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $org->update([
            'name' => $data['name'] ?? $org->name,
            'email' => $data['email'] ?? $org->email,
            'phone' => $data['phone'] ?? $org->phone,
        ]);

        return response()->json([
            'data' => [
                'id' => $org->id,
                'name' => $org->name,
                'email' => $org->email,
                'phone' => $org->phone,
            ],
        ]);
    }

    public function bankAccount(): JsonResponse
    {
        /** @var Organization|null $org */
        $org = auth()->user()->organization;
        if (! $org) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $this->authorize('viewSettings', $org);

        return response()->json([
            'data' => [
                'bankName' => $org?->bank_name,
                'iban' => $org?->iban,
            ],
        ]);
    }

    public function updateBankAccount(BankAccountRequest $request): JsonResponse
    {
        /** @var Organization|null $org */
        $org = auth()->user()->organization;
        if (! $org) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

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
}
