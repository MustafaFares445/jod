from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def replace(path: str, old: str, new: str, count: int = -1) -> None:
    target = ROOT / path
    text = target.read_text()
    if old not in text:
        if new in text:
            return
        raise RuntimeError(f'Expected text not found in {path}: {old[:100]!r}')
    target.write_text(text.replace(old, new, count))


# Eloquent passes relation instances (e.g. HasMany) to eager-load constraint
# closures. Restricting those closures to Builder causes a runtime TypeError.
replace(
    'app/Services/PostService.php',
    "static fn (Builder $builder) => $builder->where('user_id', $viewer->id)",
    "static fn ($builder) => $builder->where('user_id', $viewer->id)",
)
replace(
    'app/Services/PostService.php',
    "static fn (Builder $builder) => $builder->where('created_by', $viewer->id)",
    "static fn ($builder) => $builder->where('created_by', $viewer->id)",
)
replace(
    'app/Services/Mobile/SavedPostService.php',
    "static fn (Builder $builder) => $builder->where('created_by', $user->id)",
    "static fn ($builder) => $builder->where('created_by', $user->id)",
)

# The same policy abilities are used by platform-admin and organization report
# controllers. Allow the platform permission first, otherwise enforce org scope.
report_policy = ROOT / 'app/Policies/ReportPolicy.php'
text = report_policy.read_text()
for method, action in [
    ('claim', 'CLAIM'),
    ('requestInfo', 'REQUEST_INFO'),
    ('close', 'CLOSE'),
]:
    old = f'''    public function {method}(User $user, Report $model): bool\n    {{\n        return $this->sameOrganization($user, $model)\n            && $this->authorizeOrganizationAction($user, PermissionAction::{action});\n    }}'''
    new = f'''    public function {method}(User $user, Report $model): bool\n    {{\n        return $this->authorizeAction($user, PermissionAction::{action})\n            || ($this->sameOrganization($user, $model)\n                && $this->authorizeOrganizationAction($user, PermissionAction::{action}));\n    }}'''
    if old in text:
        text = text.replace(old, new)
    elif new not in text:
        raise RuntimeError(f'Expected {method} policy body not found')
report_policy.write_text(text)

# The gate is a named ability; authorizing viewAny against the literal string
# never invokes it.
replace(
    'app/Http/Controllers/API/Org/OverviewController.php',
    "$this->authorize('viewAny', 'org-dashboard');",
    "$this->authorize('org-dashboard');",
)

# Campaign schema/model use closed_reason. The service/resource typo silently
# dropped the close reason from persistence and responses.
replace('app/Services/CampaignService.php', "'close_reason'", "'closed_reason'")
replace('app/Http/Resources/CampaignResource.php', '$this->close_reason', '$this->closed_reason')

# Dashboard contract includes unread notifications visible to the user: direct
# inbox notifications plus organization-scoped inbox notifications.
dashboard = ROOT / 'app/Http/Controllers/API/Me/DashboardContextController.php'
text = dashboard.read_text()
if 'use App\\Models\\Notification;' not in text:
    text = text.replace('use App\\Models\\Campaign;\n', 'use App\\Models\\Campaign;\nuse App\\Models\\Notification;\n')
if 'use Illuminate\\Database\\Eloquent\\Builder;' not in text:
    text = text.replace('use App\\Services\\Permissions\\PermissionCatalogService;\n', 'use App\\Services\\Permissions\\PermissionCatalogService;\nuse Illuminate\\Database\\Eloquent\\Builder;\n')
text = text.replace(
    '/** @return array{pendingReviews: int, openReports: int} */',
    '/** @return array{unreadNotifications: int, pendingReviews: int, openReports: int} */',
)
text = text.replace(
    "return [\n                'pendingReviews' => Post::query()",
    "return [\n                'unreadNotifications' => $this->unreadNotifications($user),\n                'pendingReviews' => Post::query()",
    1,
)
text = text.replace(
    "return [\n            'pendingReviews' => Post::query()",
    "return [\n            'unreadNotifications' => $this->unreadNotifications($user),\n            'pendingReviews' => Post::query()",
    1,
)
method = '''\n    private function unreadNotifications(User $user): int\n    {\n        return Notification::query()\n            ->where('mailbox', 'inbox')\n            ->where('status', 'unread')\n            ->where(function (Builder $query) use ($user): void {\n                $query->where('recipient_id', $user->id);\n\n                if ($user->organization_id !== null) {\n                    $query->orWhere(function (Builder $organization) use ($user): void {\n                        $organization->whereNull('recipient_id')\n                            ->where('organization_id', $user->organization_id);\n                    });\n                }\n            })\n            ->count();\n    }\n'''
if 'private function unreadNotifications(User $user): int' not in text:
    pos = text.rfind('\n}')
    if pos < 0:
        raise RuntimeError('Could not insert unreadNotifications method')
    text = text[:pos] + method + text[pos:]
dashboard.write_text(text)

# Donor/applicant validation intentionally requires a campaign that belongs to
# the authenticated organization. Create the campaigns referenced by fixtures.
workspace = ROOT / 'tests/Feature/Org/OrganizationWorkspaceEndpointsTest.php'
text = workspace.read_text()
donor_fixture = '''    Campaign::factory()->create([\n        'organization_id' => $this->organization->id,\n        'title' => 'Health Initiative',\n        'status' => 'active',\n    ]);\n\n'''
if donor_fixture not in text:
    marker = "    $createPayload = [\n        'name' => 'Donor C',"
    if marker not in text:
        raise RuntimeError('Donor payload marker not found')
    text = text.replace(marker, donor_fixture + marker, 1)
applicant_fixture = '''    Campaign::factory()->create([\n        'organization_id' => $this->organization->id,\n        'title' => 'Volunteer Program',\n        'status' => 'active',\n    ]);\n\n'''
if applicant_fixture not in text:
    marker = "    $payload = [\n        'name' => 'Applicant C',"
    if marker not in text:
        raise RuntimeError('Applicant payload marker not found')
    text = text.replace(marker, applicant_fixture + marker, 1)
workspace.write_text(text)
