<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\MediaModel;
use App\Enums\NotificationEventType;
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Enums\PermissionModule;
use App\Models\Media;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Services\MediaService;
use App\Services\NotificationEventService;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyRegistrationService
{
    public function __construct(
        private readonly NotificationEventService $notifications,
        private readonly MediaService $mediaService,
    ) {}

    /** @param array<string, mixed> $data */
    public function register(array $data, UploadedFile $logo): User
    {
        /** @var Media|null $storedLogo */
        $storedLogo = null;

        try {
            [$user, $organization] = DB::transaction(function () use ($data, $logo, &$storedLogo): array {
                $organization = Organization::query()->create([
                    'name' => $data['companyName'],
                    'email' => $data['companyEmail'],
                    'phone' => $data['companyPhone'],
                    'organization_number' => $data['organizationNumber'],
                    'registration_number' => $data['registrationNumber'],
                    'bank_account_number' => $data['bankAccountNumber'],
                    'location' => $data['location'],
                    'website' => $data['website'] ?? null,
                    'owner_full_name' => $data['ownerName'],
                    'owner_email' => $data['companyEmail'],
                    'owner_phone' => $data['companyPhone'],
                    'status' => 'pending',
                    'verification_status' => 'pending',
                    'last_active_at' => now(),
                ]);

                $user = User::query()->create([
                    'name' => $data['ownerName'],
                    'email' => $data['companyEmail'],
                    'phone' => $data['companyPhone'],
                    'password' => $data['password'],
                    'user_type' => 'general',
                    'organization_id' => $organization->id,
                    'status' => 'active',
                    'last_active_at' => now(),
                ]);

                $ownerRole = OrganizationRole::query()->create([
                    'organization_id' => $organization->id,
                    'name' => 'المالك',
                    'description' => 'صلاحية كاملة لإدارة المؤسسة وجميع أقسامها.',
                    'permissions' => array_merge(
                        [PermissionNameResolver::resolve(PermissionGroup::DASHBOARD, PermissionAction::VIEW)],
                        $this->organizationPermissionNames(),
                    ),
                    'is_active' => true,
                    'is_system' => true,
                    'members_count' => 1,
                ]);

                OrganizationStaff::query()->create([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'organization_role_id' => $ownerRole->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => 'active',
                    'invited_at' => now(),
                    'accepted_at' => now(),
                ]);

                try {
                    $storedLogo = $this->mediaService->upload(
                        MediaModel::ORGANIZATION,
                        (string) $organization->id,
                        'logo',
                        $logo,
                    );
                } catch (Throwable $exception) {
                    report($exception);
                    throw ValidationException::withMessages([
                        'logo' => ['تعذر رفع شعار المنظمة، لذلك لم يتم إنشاء المنظمة. حاول رفع الصورة مرة أخرى.'],
                    ]);
                }

                return [$user, $organization];
            });
        } catch (Throwable $exception) {
            if ($storedLogo !== null) {
                Storage::disk($storedLogo->disk)->delete($storedLogo->path);
            }
            throw $exception;
        }

        try {
            $this->notifications->notifyUser(
                $user,
                NotificationEventType::OrganizationSubmitted,
                'تم إرسال طلب تسجيل المؤسسة',
                "تم إرسال طلب تسجيل {$organization->name} وهو الآن بانتظار مراجعة الإدارة.",
                'account',
                'normal',
                $organization->name,
                '/organization/profile',
                (string) $organization->id,
            );

            $this->notifications->notifyAdmins(
                NotificationEventType::OrganizationSubmitted,
                'مؤسسة جديدة بانتظار المراجعة',
                "تم تسجيل {$organization->name} وتحتاج إلى مراجعة وتوثيق.",
                'account',
                'high',
                $organization->name,
                '/admin/organizations/'.$organization->id,
                (string) $user->id,
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return $user->refresh()->loadMissing('organization.logoMedia');
    }

    /** @return list<string> */
    private function organizationPermissionNames(): array
    {
        return PermissionCatalog::permissions()
            ->filter(fn (array $permission): bool => $permission['group']->module() === PermissionModule::ORGANIZATION)
            ->pluck('name')
            ->values()
            ->all();
    }
}
