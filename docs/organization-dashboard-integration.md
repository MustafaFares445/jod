# Organization Dashboard Integration

## Scope completed

The organization owner and staff dashboards use the same organization-scoped backend contracts with server-side authorization and frontend permission gating.

### Authentication and authorization

- `GET /api/v1/me/dashboard-context` supplies dashboard role, organization membership, and flattened permissions.
- Organization routes return `422` when the authenticated user has no organization.
- Owner-only staff and role management remains protected by policies.
- Cross-organization resources are rejected by model policies.

### Organization APIs

- Overview: `GET /api/v1/org/dashboard/overview`
- Campaigns: CRUD with status transitions on `PATCH /api/v1/org/campaigns/{campaign}`
- Posts: CRUD plus `PATCH /api/v1/org/posts/{post}/status`
- Donors: `/api/v1/org/donors`
- Applicants: `/api/v1/org/applicants`
- Staff and roles: `/api/v1/org/staff`, `/api/v1/org/staff/roles`
- Permission catalog: `/api/v1/org/permissions/catalog`
- Reports: list/detail plus `PATCH /api/v1/org/reports/{report}/status`
- Audit log: `GET /api/v1/org/audit-logs`
- Profile/settings: `/api/v1/org/settings/profile`, `/api/v1/org/settings/bank-account`

### Canonical permissions

- `dashboard.view`
- `org.campaigns.*`
- `org.posts.*`
- `org.donors.*`
- `org.applicants.*`
- `org.reports.view`, `org.reports.update`
- `org.audit_logs.view`
- `org.settings.view`, `org.settings.update`

Notifications are intentionally excluded from the assignable organization permission catalog for this delivery.

## Deferred scope

- Organization notifications UI/API integration
- Mobile application backend integration
- Mobile-only compatibility endpoints

## Verification

- Backend feature tests were added for report status transitions and permission-based audit access.
- The current local environment cannot execute PHP (`spawn php ENOENT`), so backend tests must be run in a PHP 8.3+ environment with Composer dependencies installed.
- Recommended command: `php artisan test tests/Feature/Org`.
