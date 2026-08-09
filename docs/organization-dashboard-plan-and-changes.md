# Organization Dashboard Plan and Implementation Changes

> This file exists with identical content in both the Backend and Frontend repositories and serves as the shared reference for the Organization Owner and Organization Staff dashboard implementation.

## Document Information

| Item | Value |
|---|---|
| Status | Completed and implemented |
| Last updated | 2026-07-31 |
| Scope | Organization Owner Dashboard + Organization Staff Dashboard |
| Backend | Laravel API in `BE-JOD/jod` |
| Frontend | Next.js in `JOD-FrontEnd` |
| Out of scope | Organization Notifications and Mobile Backend Integration |

---

## 1. Plan Objective

The objective is to deliver a unified Full Stack integration for the organization dashboard so that:

- The Backend is the source of truth for permissions, contracts, and data.
- Organization Owner and Organization Staff use the same organization-scoped APIs.
- Pages, navigation items, and actions differ based on the authenticated user's permissions.
- Backend Policies prevent access to data owned by another organization.
- Permission names are identical across Backend and Frontend.
- Completed sections use real API data instead of static data.
- Lifecycle operations use canonical status endpoints instead of separate action-specific frontend integrations.

---

## 2. Implementation Scope

### Included

- Authentication Context and permission refresh.
- Isolation between Admin, Organization Owner, and Organization Staff.
- Organization Overview Dashboard.
- Staff Management.
- Roles and Permission Catalog.
- Campaigns.
- Posts.
- Donors.
- Applicants.
- Reports.
- Organization Audit Log.
- Organization Profile and Settings.
- Staff Personal Profile.
- Route Guards and Navigation Guards.
- Backend feature tests and shared contracts.

### Excluded from the current delivery

- Organization Notifications UI/API integration.
- Mobile Application Backend integration.
- Mobile-only compatibility endpoints.

Existing notification source files were not removed. Their organization navigation links are hidden and guarded until notifications are implemented as a separate phase.

---

## 3. Architectural Principles

### 3.1 Backend as the permission source of truth

Dashboard context is loaded from:

```http
GET /api/v1/me/dashboard-context
```

It contains:

- Authenticated user data.
- Dashboard role/type.
- The user's organization membership.
- Hierarchical permissions in `modules`.
- Direct permission lookup in `flat`.
- Granted permission names in `granted`.

If a non-admin user is not linked to an organization, the Backend returns `422` instead of generating an incomplete organization dashboard context.

### 3.2 Organization scoping

All organization resources are scoped using the authenticated user's `organization_id`. Security does not rely on hidden buttons only. It is enforced through:

- Laravel Policies.
- Resource ownership checks.
- Service-level filters.
- Route authorization.

### 3.3 Permission-aware Frontend

The Frontend uses `AuthProvider` and:

```ts
can('permission.name')
```

This controls:

- Sidebar items.
- Section tabs.
- Create, update, and delete buttons.
- Lifecycle actions.
- Optional query execution.
- Direct route access.
- Redirects to the first allowed route.

### 3.4 Canonical API contracts

Canonical status endpoints were introduced:

```http
PATCH /api/v1/org/campaigns/{campaign}/status
PATCH /api/v1/org/posts/{post}/status
PATCH /api/v1/org/reports/{report}/status
```

Legacy action endpoints may remain in the Backend for temporary compatibility, but the new Frontend integration uses the canonical contracts.

---

## 4. Implementation Phases

| Phase | Content | Status |
|---|---|---|
| Phase 1 | Authentication Context and Permission Navigation | Completed |
| Phase 2 | Staff, Roles, and Permission Catalog | Completed |
| Phase 3 | Organization Overview | Completed |
| Phase 4 | Campaigns and Posts Lifecycle | Completed |
| Phase 5 | Donors and Applicants | Completed |
| Phase 6 | Reports and Audit Log | Completed |
| Phase 7 | Profile and Settings | Completed |
| Phase 8 | Tests, Cleanup, and Documentation | Completed |

---

## 5. Changes by Section

## 5.1 Authentication and Permissions

### Backend changes

- Updated `/me/dashboard-context` to return complete organization and permission context.
- Added the organization audit log permission group `org.audit_logs`.
- Added the report update permission `org.reports.update`.
- Removed organization notification permissions from the assignable catalog for this delivery.
- Prevented organization dashboard context generation for users without an organization.
- Removed organization notification counters from the current dashboard context.

### Frontend changes

- Hydrated Dashboard Context in `AuthProvider` on login and refresh.
- Added `can(permission)` as the unified permission check.
- Protected dashboard layouts.
- Filtered Sidebar and Section Tabs by permission.
- Isolated Admin, Owner, and Staff routes.
- Redirected unauthorized direct routes to the first allowed route.
- Hid organization notification routes from current navigation.

---

## 5.2 Staff Management, Roles, and Permission Catalog

### Backend changes

- Updated role requests to accept `isActive` and map it to `is_active`.
- Added `roleId` to the Staff Resource contract.
- Protected Staff and Roles using Policies.
- Preserved safeguards that prevent unsafe system-role changes or removal of the last owner.
- Exposed the permission catalog through:

```http
GET /api/v1/org/permissions/catalog
```

- Kept Staff and Roles management owner-only and excluded them from assignable staff permissions.

### Frontend changes

- Removed static roles and static permission catalog data.
- Loaded roles from:

```http
GET /api/v1/org/staff/roles
```

- Used `organizationRoleId` during staff create and update operations.
- Connected staff and role forms to real APIs.
- Restricted Staff and Roles pages to the organization owner.
- Built permission fields dynamically from the Backend catalog.

---

## 5.3 Organization Overview

### Backend changes

Exposed:

```http
GET /api/v1/org/dashboard/overview
```

The response includes:

- Campaign statistics.
- Post statistics.
- Donor, applicant, and report statistics when permitted.
- `recentActivity` instead of the previous `activity` key.

### Frontend changes

- Removed static Overview data for Owner and Staff.
- Created a shared Overview component for both dashboard types.
- Loaded data from `/org/dashboard/overview`.
- Hid statistics for sections the user cannot view.
- Added loading, error, and empty states.

---

## 5.4 Campaigns

### Backend changes

- Full CRUD through:

```http
/api/v1/org/campaigns
```

- Canonical status endpoint:

```http
PATCH /api/v1/org/campaigns/{campaign}/status
```

- Supported statuses:
  - `draft`
  - `active`
  - `closed`
- Supported `closedReason` when closing a campaign.
- Protected view, create, update, close, and delete operations using Policies.
- Rejected access to campaigns owned by another organization.

### Frontend changes

- Replaced the `/close` integration with the canonical `/status` contract.
- Sends:

```json
{
  "status": "closed",
  "closedReason": "..."
}
```

- Protected actions using:
  - `org.campaigns.create`
  - `org.campaigns.update`
  - `org.campaigns.close`
  - `org.campaigns.delete`
- Protected the section itself using `org.campaigns.view`.
- Connected main campaign operations to real API data.

---

## 5.5 Posts

### Backend changes

- Full CRUD through:

```http
/api/v1/org/posts
```

- Canonical status endpoint:

```http
PATCH /api/v1/org/posts/{post}/status
```

- Supported statuses:
  - `draft`
  - `published`
  - `archived`
- Enforced lifecycle transition rules in the Backend.
- Protected Publish, Archive, and Restore using separate permissions.

### Frontend changes

- Stopped using separate Publish, Archive, and Restore endpoints in the new integration.
- Uses the canonical status contract:

```json
{ "status": "published" }
{ "status": "archived" }
{ "status": "draft" }
```

- Protected operations using:
  - `org.posts.create`
  - `org.posts.update`
  - `org.posts.publish`
  - `org.posts.archive`
  - `org.posts.restore`
  - `org.posts.delete`

---

## 5.6 Donors

### Backend changes

- Full CRUD through:

```http
/api/v1/org/donors
```

- Scoped data to the authenticated user's organization.
- Protected View, Create, Update, and Delete with Policies.

### Frontend changes

- Connected list, create, edit, and delete operations to the API.
- Runs the Donors query only when the donor section is active and the user has view permission.
- Protected actions using:
  - `org.donors.create`
  - `org.donors.update`
  - `org.donors.delete`

---

## 5.7 Applicants

### Backend changes

- Independent CRUD through:

```http
/api/v1/org/applicants
```

- Kept applicants separate from donors at the API level.
- Scoped results to the authenticated user's organization.
- Protected View, Create, Update, and Delete using Policies.

### Frontend changes

- Uses an independent Applicants query.
- Does not run the Applicants query while the Donors section is active, and vice versa.
- Protected actions using:
  - `org.applicants.create`
  - `org.applicants.update`
  - `org.applicants.delete`

---

## 5.8 Reports

### Backend changes

- List and detail endpoints:

```http
GET /api/v1/org/reports
GET /api/v1/org/reports/{report}
```

- Added the canonical status endpoint:

```http
PATCH /api/v1/org/reports/{report}/status
```

- Supported transitions:
  - `new -> in_progress`
  - `in_progress -> waiting_response`
  - `in_progress -> closed`
  - `waiting_response -> closed`
- Reused existing service methods to avoid duplicating business logic.
- Protected updates with `org.reports.update`.
- Rejected updates to reports owned by another organization.

### Frontend changes

- Created organization-specific reports service and query hooks.
- Displays actual `ReportResource` fields:
  - `title`
  - `description`
  - `severity`
  - `status`
  - `reporterName`
  - `createdAt`
- Displays status controls only when the user has `org.reports.update`.
- Prevents invalid transitions based on the current report status.

---

## 5.9 Organization Audit Log

### Backend changes

- Uses:

```http
GET /api/v1/org/audit-logs
```

- Always allows the organization owner.
- Allows organization staff only when they have:

```text
org.audit_logs.view
```

- Scopes logs by the actor's organization.
- Supports filters:
  - `actorUserId`
  - `action`
  - `from`
  - `to`

### Frontend changes

- Removed the Admin Audit Log hook from organization pages.
- Added:
  - `org.audit-logs.services.ts`
  - `org.audit-logs.query.ts`
  - `org.audit-logs.types.ts`
- Sends requests to `/org/audit-logs` instead of `/admin/audit-logs`.
- Protects route and navigation access using `org.audit_logs.view`.

---

## 5.10 Organization Profile and Settings

### Backend changes

Provided:

```http
GET   /api/v1/org/settings/profile
PATCH /api/v1/org/settings/profile
GET   /api/v1/org/settings/bank-account
PATCH /api/v1/org/settings/bank-account
```

Organization data includes:

- Name.
- Email.
- Phone.
- Bank name.
- IBAN.

Access is protected by:

- `org.settings.view`
- `org.settings.update`

### Frontend changes

- Removed local-only organization settings behavior.
- Created organization settings services and query hooks.
- Connected Owner and Staff settings pages to the organization settings APIs.
- Displays read-only fields when the user lacks update permission.
- Split Profile behavior:
  - Organization Owner updates organization profile data.
  - Organization Staff updates personal account data through `/me/profile` only.

---

## 5.11 Organization Notifications

This section is intentionally deferred.

### Current Backend state

- Routes or models may remain for compatibility or future development.
- Notification permissions are not included in the assignable permission catalog for this delivery.

### Current Frontend state

- Next.js notification route files may still exist and appear in the build output.
- Notification links are not shown in Sidebar or Tabs.
- Direct route guards redirect users to an allowed route.
- Normal organization dashboard usage does not trigger notification API requests.

---

## 6. Main API Reference

| Section | Method | Endpoint | Purpose |
|---|---|---|---|
| Auth | GET | `/api/v1/me/dashboard-context` | User, organization, and permissions |
| Overview | GET | `/api/v1/org/dashboard/overview` | Organization statistics and recent activity |
| Campaigns | REST | `/api/v1/org/campaigns` | Campaign CRUD |
| Campaign Status | PATCH | `/api/v1/org/campaigns/{id}/status` | Campaign lifecycle update |
| Posts | REST | `/api/v1/org/posts` | Post CRUD |
| Post Status | PATCH | `/api/v1/org/posts/{id}/status` | Post lifecycle update |
| Donors | REST | `/api/v1/org/donors` | Donor CRUD |
| Applicants | REST | `/api/v1/org/applicants` | Applicant CRUD |
| Staff | REST | `/api/v1/org/staff` | Staff management |
| Roles | REST | `/api/v1/org/staff/roles` | Organization role management |
| Permissions | GET | `/api/v1/org/permissions/catalog` | Assignable permission catalog |
| Reports | GET | `/api/v1/org/reports` | Report list |
| Report Detail | GET | `/api/v1/org/reports/{id}` | Report detail |
| Report Status | PATCH | `/api/v1/org/reports/{id}/status` | Report lifecycle update |
| Audit Log | GET | `/api/v1/org/audit-logs` | Organization audit log |
| Org Profile | GET/PATCH | `/api/v1/org/settings/profile` | Organization profile data |
| Bank Account | GET/PATCH | `/api/v1/org/settings/bank-account` | Organization bank account |
| Staff Profile | PATCH | `/api/v1/me/profile` | Staff personal account data |

---

## 7. Permission Matrix

| Section | View and action permissions |
|---|---|
| Dashboard | `dashboard.view` |
| Campaigns | `org.campaigns.view/create/update/close/delete` |
| Posts | `org.posts.view/create/update/publish/archive/restore/delete` |
| Donors | `org.donors.view/create/update/delete` |
| Applicants | `org.applicants.view/create/update/delete` |
| Reports | `org.reports.view/update` |
| Audit Log | `org.audit_logs.view` |
| Settings | `org.settings.view/update` |
| Staff and Roles | Owner-only and not part of the assignable catalog |
| Notifications | Deferred and not assignable in this delivery |

Every mutating permission logically requires the matching section view permission. The Backend permission catalog returns this dependency in `requires`.

---

## 8. Security Rules

1. Hiding a button in the Frontend is not a replacement for a Backend Policy.
2. Every Show, Update, and Delete operation must verify `organization_id` ownership.
3. The organization owner retains sensitive organization management capabilities.
4. Organization staff receive only permissions assigned through their role.
5. Organization pages must never use Admin APIs.
6. The Frontend does not send `organizationId` to select the organization; it is resolved from the authenticated user.
7. Lifecycle transitions are validated in Backend services/domain logic, not only in the Frontend.
8. When route permission is missing, the user is redirected to the first allowed route instead of seeing a partially functional page.

---

## 9. Verification and Testing

### Frontend

Verified using:

```bash
npm run lint
npm run build
```

Current result:

- Lint passed with zero errors.
- Existing non-blocking warnings remain.
- Production build passed.
- TypeScript validation passed.
- Next.js reports the existing `middleware` to `proxy` deprecation warning.
- No Frontend test runner is currently configured in `package.json`.

### Backend

Feature tests cover:

- Organization scoping.
- Campaign and Post lifecycle contracts.
- Report status contract.
- Owner audit log access.
- Staff audit log access with permission.
- Rejection of staff without audit log permission.
- Organization Profile and Bank Settings.

Run with:

```bash
php artisan test tests/Feature/Org
```

The tests could not be executed in the current environment because `php` is not installed. The environment returned:

```text
spawn php ENOENT
```

---

## 10. Main Commit Map

### Backend

```text
abe3781 feat(org-dashboard): finalize auth context permissions
6a5ac1d feat(org-dashboard): align staff role contracts
4a2d304 feat(org-dashboard): expose overview activity contract
7b5bb61 feat(org-dashboard): connect reports audit permissions
2e75132 test(org-dashboard): cover reports audit integration
```

### Frontend

```text
777e8ef feat(org-dashboard): enforce auth permission navigation
a51f1b1 feat(org-dashboard): connect staff roles permissions
583ed2a feat(org-dashboard): connect organization overview
dfc7aa0 feat(org-dashboard): align campaign post lifecycle permissions
89683aa feat(org-dashboard): gate donor applicant operations
2d95d5d feat(org-dashboard): connect organization reports audit
6ec4cc2 feat(org-dashboard): connect organization settings
3b176eb feat(org-dashboard): separate owner staff profiles
a58ea43 docs(org-dashboard): record integration verification
```

---

## 11. Rules for Adding a New Organization Section

When adding a new section to the organization dashboard:

1. Define the Backend Permission Group and actions.
2. Add permissions to the catalog when they are assignable.
3. Create a Policy and enforce organization scoping.
4. Create a clear camelCase API Resource contract.
5. Create Frontend types, services, query keys, and query hooks.
6. Protect Route, Sidebar, and Tabs using the view permission.
7. Protect every action with its specific permission.
8. Disable unauthorized queries instead of relying only on a `403` response.
9. Add Feature tests for the contract, permissions, and cross-organization access.
10. Update this file in both Backend and Frontend repositories.

---

## 12. Final Status

- Core Backend and Frontend integration is complete.
- Owner and Staff use the same organization APIs.
- Permission names are aligned across both repositories.
- Organization scoping is enforced in the Backend.
- Completed sections use real API data.
- Notifications and Mobile integration are explicitly deferred.
- Future work must preserve the canonical contracts and permission rules documented here.
