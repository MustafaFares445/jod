<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\NotificationEventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function __construct(private readonly NotificationEventService $notifications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Organization::class);

        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $sort = (string) ($request->query('sort') ?? '');
        $sortBy = (string) ($request->query('sortBy') ?? '');

        $query = Organization::query()
            ->when($this->queryParam($request, 'filter.verificationStatus') && $this->queryParam($request, 'filter.verificationStatus') !== 'all', fn (Builder $builder) => $builder->where('verification_status', $this->queryParam($request, 'filter.verificationStatus')))
            ->when($this->queryParam($request, 'filter.status') && $this->queryParam($request, 'filter.status') !== 'all', fn (Builder $builder) => $builder->where('status', $this->queryParam($request, 'filter.status')))
            ->when($this->queryParam($request, 'filter.location') && $this->queryParam($request, 'filter.location') !== 'all', fn (Builder $builder) => $builder->where('location', 'like', '%'.$this->queryParam($request, 'filter.location').'%'))
            ->when($this->queryParam($request, 'filter.search') && $this->queryParam($request, 'filter.search') !== 'all', function (Builder $builder) use ($request): void {
                $search = (string) $this->queryParam($request, 'filter.search');
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            });

        $normalizedSort = $sort !== '' ? $sort : match ($sortBy) {
            'name_asc' => 'name',
            'name_desc' => '-name',
            'created_oldest' => 'createdAt',
            'created_newest' => '-createdAt',
            default => '-createdAt',
        };

        match ($normalizedSort) {
            'name' => $query->orderBy('name'),
            '-name' => $query->orderByDesc('name'),
            'createdAt' => $query->orderBy('created_at'),
            '-createdAt' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return OrganizationResource::collection($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:organizations,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'location' => ['nullable', 'string', 'max:255'],
            'organizationType' => ['nullable', 'string', 'max:255'],
            'registrationNumber' => ['nullable', 'string', 'max:255'],
            'establishmentDate' => ['nullable', 'date'],
            'shortAddress' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'licenseDocumentName' => ['nullable', 'string', 'max:255'],
            'delegationDocumentName' => ['nullable', 'string', 'max:255'],
            'ownerFullName' => ['nullable', 'string', 'max:255'],
            'ownerEmail' => ['nullable', 'email', 'max:255'],
            'ownerPhone' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'string', 'max:255'],
            'socialMedia' => ['nullable', 'array'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'pending'])],
            'verificationStatus' => ['nullable', Rule::in(['verified', 'unverified', 'pending'])],
        ]);

        $status = $data['status'] ?? match ($data['verificationStatus'] ?? null) {
            'verified' => 'active',
            'pending' => 'pending',
            default => 'inactive',
        };

        $organization = Organization::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'location' => $data['location'] ?? null,
            'organization_type' => $data['organizationType'] ?? null,
            'registration_number' => $data['registrationNumber'] ?? null,
            'establishment_date' => $data['establishmentDate'] ?? null,
            'short_address' => $data['shortAddress'] ?? null,
            'description' => $data['description'] ?? null,
            'license_document_name' => $data['licenseDocumentName'] ?? null,
            'delegation_document_name' => $data['delegationDocumentName'] ?? null,
            'owner_full_name' => $data['ownerFullName'] ?? null,
            'owner_email' => $data['ownerEmail'] ?? null,
            'owner_phone' => $data['ownerPhone'] ?? null,
            'website' => $data['website'] ?? null,
            'social_media' => $data['socialMedia'] ?? null,
            'status' => $status,
            'verification_status' => $this->verificationStatusFor($status),
        ]);

        return OrganizationResource::make($organization)->response()->setStatusCode(201);
    }

    public function show(Organization $organization): OrganizationResource
    {
        $this->authorize('view', $organization);

        return OrganizationResource::make($organization);
    }

    public function update(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorize('update', $organization);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('organizations', 'email')->ignore($organization->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'organizationType' => ['sometimes', 'nullable', 'string', 'max:255'],
            'registrationNumber' => ['sometimes', 'nullable', 'string', 'max:255'],
            'establishmentDate' => ['sometimes', 'nullable', 'date'],
            'shortAddress' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'licenseDocumentName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'delegationDocumentName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ownerFullName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ownerEmail' => ['sometimes', 'nullable', 'email', 'max:255'],
            'ownerPhone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'socialMedia' => ['sometimes', 'nullable', 'array'],
        ]);

        $organization->update([
            'name' => $data['name'] ?? $organization->name,
            'email' => $data['email'] ?? $organization->email,
            'phone' => $data['phone'] ?? $organization->phone,
            'location' => $data['location'] ?? $organization->location,
            'organization_type' => $data['organizationType'] ?? $organization->organization_type,
            'registration_number' => $data['registrationNumber'] ?? $organization->registration_number,
            'establishment_date' => $data['establishmentDate'] ?? $organization->establishment_date,
            'short_address' => $data['shortAddress'] ?? $organization->short_address,
            'description' => $data['description'] ?? $organization->description,
            'license_document_name' => $data['licenseDocumentName'] ?? $organization->license_document_name,
            'delegation_document_name' => $data['delegationDocumentName'] ?? $organization->delegation_document_name,
            'owner_full_name' => $data['ownerFullName'] ?? $organization->owner_full_name,
            'owner_email' => $data['ownerEmail'] ?? $organization->owner_email,
            'owner_phone' => $data['ownerPhone'] ?? $organization->owner_phone,
            'website' => $data['website'] ?? $organization->website,
            'social_media' => $data['socialMedia'] ?? $organization->social_media,
        ]);

        return OrganizationResource::make($organization->refresh());
    }

    public function destroy(Organization $organization): Response
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return response()->noContent();
    }

    public function updateStatus(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorize('update', $organization);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $this->applyAccountStatus($organization, $data['status']);

        return OrganizationResource::make($organization->refresh());
    }

    public function updateVerification(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorize('verify', $organization);

        $data = $request->validate([
            'verificationStatus' => ['required', Rule::in(['verified', 'unverified'])],
        ]);

        $this->applyAccountStatus(
            $organization,
            $data['verificationStatus'] === 'verified' ? 'active' : 'inactive',
        );

        return OrganizationResource::make($organization->refresh());
    }

    public function accept(Request $request, Organization $organization): OrganizationResource
    {
        $this->authorize('accept', $organization);

        $wasAccepted = $organization->accepted_at !== null;
        $this->applyAccountStatus($organization, 'active', notify: false);

        if (! $wasAccepted) {
            $this->notifications->notifyOrganization(
                (string) $organization->id,
                NotificationEventType::OrganizationApproved,
                'تم قبول المؤسسة',
                "تم قبول {$organization->name} وتفعيل حساب المؤسسة على المنصة.",
                'account',
                'high',
                $organization->name,
                '/organization/profile',
                auth()->id() !== null ? (string) auth()->id() : null,
            );
        }

        return OrganizationResource::make($organization->refresh());
    }

    private function applyAccountStatus(Organization $organization, string $status, bool $notify = true): void
    {
        $previousStatus = (string) $organization->status;
        $organization->update([
            'status' => $status,
            'verification_status' => $this->verificationStatusFor($status),
            'accepted_at' => $status === 'active'
                ? ($organization->accepted_at ?? now())
                : $organization->accepted_at,
        ]);

        if ($status !== 'active') {
            User::query()
                ->where('organization_id', $organization->id)
                ->each(fn (User $user) => $user->tokens()->delete());
        }

        if ($notify && $previousStatus !== $status) {
            $active = $status === 'active';
            $this->notifications->notifyOrganization(
                (string) $organization->id,
                $active ? NotificationEventType::OrganizationApproved : NotificationEventType::OrganizationRejected,
                $active ? 'تم تفعيل المؤسسة وتوثيقها' : 'تم إلغاء تفعيل المؤسسة',
                $active
                    ? "تم تفعيل {$organization->name} وتوثيقها بنجاح على المنصة."
                    : "تم إلغاء تفعيل {$organization->name} وأصبحت غير موثقة على المنصة.",
                'account',
                'high',
                $organization->name,
                '/organization/profile',
                auth()->id() !== null ? (string) auth()->id() : null,
            );
        }
    }

    private function verificationStatusFor(string $status): string
    {
        return match ($status) {
            'active' => 'verified',
            'pending' => 'pending',
            default => 'unverified',
        };
    }

    private function queryParam(Request $request, string $key): mixed
    {
        $queryParams = $request->query();

        if (array_key_exists($key, $queryParams)) {
            return $queryParams[$key];
        }

        $flatKey = str_replace('.', '_', $key);
        if (array_key_exists($flatKey, $queryParams)) {
            return $queryParams[$flatKey];
        }

        return data_get($queryParams, $key);
    }
}
