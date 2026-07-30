<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Support\Collection;

class OrganizationOverviewService
{
    /** @return array{stats: list<array<string, mixed>>, activity: list<array<string, mixed>>} */
    public function overview(User $user, Organization $organization): array
    {
        return [
            'stats' => $this->stats($user, $organization),
            'activity' => $this->activity($user, $organization),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function stats(User $user, Organization $organization): array
    {
        $organizationId = (string) $organization->id;
        $stats = collect();

        if ($this->canView($user, PermissionGroup::ORG_CAMPAIGN)) {
            $stats->push([
                'id' => 'campaigns',
                'label' => 'Campaigns',
                'value' => Campaign::query()->where('organization_id', $organizationId)->count(),
                'hint' => Campaign::query()
                    ->where('organization_id', $organizationId)
                    ->where('status', 'active')
                    ->count().' active',
            ]);
        }

        if ($this->canView($user, PermissionGroup::ORG_POST)) {
            $stats->push([
                'id' => 'posts',
                'label' => 'Posts',
                'value' => Post::query()->where('organization_id', $organizationId)->count(),
                'hint' => Post::query()
                    ->where('organization_id', $organizationId)
                    ->where('status', 'published')
                    ->count().' published',
            ]);
        }

        if ($this->canView($user, PermissionGroup::ORG_DONOR)) {
            $stats->push([
                'id' => 'donors',
                'label' => 'Donors',
                'value' => Donation::query()->where('organization_id', $organizationId)->count(),
                'hint' => 'Organization donations',
            ]);
        }

        if ($this->canView($user, PermissionGroup::ORG_APPLICANT)) {
            $stats->push([
                'id' => 'applicants',
                'label' => 'Applicants',
                'value' => CampaignApplication::query()->where('organization_id', $organizationId)->count(),
                'hint' => 'Campaign applicants',
            ]);
        }

        if ($user->isOrganizationOwner()) {
            $stats->push([
                'id' => 'staff',
                'label' => 'Staff',
                'value' => $organization->staff()->count(),
                'hint' => $organization->staff()->where('status', 'active')->count().' active',
            ]);
        }

        if ($this->canView($user, PermissionGroup::ORG_REPORT)) {
            $stats->push([
                'id' => 'reports',
                'label' => 'Reports',
                'value' => Report::query()->where('organization_id', $organizationId)->count(),
                'hint' => Report::query()
                    ->where('organization_id', $organizationId)
                    ->whereIn('status', ['new', 'in_progress'])
                    ->count().' open',
            ]);
        }

        return $stats->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function activity(User $user, Organization $organization): array
    {
        $allowedEntityTypes = $this->allowedActivityEntityTypes($user);

        if ($allowedEntityTypes === []) {
            return [];
        }

        return AuditLog::query()
            ->with('actor:id,name,organization_id')
            ->whereHas('actor', fn ($query) => $query->where('organization_id', $organization->id))
            ->whereIn('entity_type', $allowedEntityTypes)
            ->latest('at')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => (string) $log->id,
                'title' => $this->activityTitle($log->action),
                'detail' => $log->metadata['name'] ?? $log->metadata['title'] ?? $log->entity_type,
                'action' => $log->action,
                'entityType' => $log->entity_type,
                'entityId' => (string) $log->entity_id,
                'actor' => $log->actor?->name,
                'at' => $log->at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<string> */
    private function allowedActivityEntityTypes(User $user): array
    {
        $types = collect();

        $this->appendEntityTypes($types, $user, PermissionGroup::ORG_CAMPAIGN, ['Campaign']);
        $this->appendEntityTypes($types, $user, PermissionGroup::ORG_POST, ['Post']);
        $this->appendEntityTypes($types, $user, PermissionGroup::ORG_DONOR, ['Donation', 'Donor']);
        $this->appendEntityTypes($types, $user, PermissionGroup::ORG_APPLICANT, ['CampaignApplication', 'Applicant']);
        $this->appendEntityTypes($types, $user, PermissionGroup::ORG_REPORT, ['Report']);

        if ($user->isOrganizationOwner()) {
            $types->push('OrganizationStaff', 'OrganizationRole', 'Organization');
        }

        return $types->unique()->values()->all();
    }

    /** @param list<string> $entityTypes */
    private function appendEntityTypes(Collection $types, User $user, PermissionGroup $group, array $entityTypes): void
    {
        if ($this->canView($user, $group)) {
            $types->push(...$entityTypes);
        }
    }

    private function canView(User $user, PermissionGroup $group): bool
    {
        return $user->isOrganizationOwner()
            || $user->can(PermissionNameResolver::resolve($group, PermissionAction::VIEW));
    }

    private function activityTitle(string $action): string
    {
        return str($action)->replace('.', ' ')->headline()->toString();
    }
}
