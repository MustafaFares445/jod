# JOD Organization API Contract

**Version:** 1.0.0  
**Status:** Target contract  
**API prefix:** `/api/v1/org`  
**Authentication:** Laravel Sanctum bearer token  
**Consumers:** Org-Owner Dashboard and Org-Staff Dashboard

---

## 1. Purpose

This document is the authoritative HTTP contract for organization workspace APIs in the JOD platform.

It defines:

- authentication and organization scoping;
- role and permission behavior;
- success and error envelopes;
- pagination, sorting, and search conventions;
- request and response schemas;
- endpoint paths and lifecycle rules;
- compatibility aliases used during frontend migration.

The API must never expose data from an organization other than the organization linked to the authenticated user.

The words **MUST**, **MUST NOT**, **SHOULD**, and **MAY** are normative.

---

## 2. Base URL and Authentication

All endpoints in this contract use the following prefix:

```text
/api/v1/org
```

All endpoints require a Sanctum bearer token:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

Unauthenticated requests return `401`.

```json
{
  "type": "https://jod.example/errors/unauthenticated",
  "title": "Unauthenticated",
  "status": 401,
  "traceId": "01J...",
  "detail": "A valid bearer token is required."
}
```

---

## 3. Organization Scoping

Every organization resource MUST be scoped using the authenticated user’s `organization_id`.

A request for a resource that exists but belongs to another organization MUST return `404`, not `403`. This prevents resource enumeration.

The following resource types are organization-scoped:

- campaigns;
- posts;
- donors and applicants;
- staff members;
- staff roles;
- notifications;
- reports;
- audit-log entries;
- profile and settings data;
- overview statistics and activity.

A user without an organization assignment receives `422`:

```json
{
  "type": "https://jod.example/errors/validation",
  "title": "Validation failed",
  "status": 422,
  "traceId": "01J...",
  "errors": {
    "organizationId": [
      "Authenticated user is not linked to an organization."
    ]
  }
}
```

---

## 4. Roles and Permissions

### 4.1 Dashboard roles

| Role | Meaning |
|---|---|
| `org_owner` | Organization owner with organization administration capabilities. |
| `org_staff` | Organization staff member whose access is restricted by assigned permissions. |

### 4.2 Permission catalog

These are the only valid organization dashboard permission identifiers:

| Permission ID | Capability |
|---|---|
| `campaigns-manage` | Create, update, change status, and delete campaigns. |
| `posts-manage` | Create, update, change status, and delete posts. |
| `donors-manage` | Create, update, and delete donor/applicant entries. |
| `donors-view` | View donor/applicant entries. |
| `staff-manage` | Manage staff members and staff roles. |
| `notifications-manage` | Send, update, resend, and delete notifications. |
| `notifications-view` | View notifications and mark inbox notifications as read. |
| `reports-view` | View reports and update report workflow status. |
| `dashboard-view` | View organization dashboard overview data. |
| `settings-manage` | Update profile, account, security, and bank settings. |

### 4.3 Role restrictions

Org Staff MUST NOT access staff or role administration endpoints, even when a malformed or legacy permission assignment contains `staff-manage`.

The following endpoint groups are owner-only:

```text
/staff
/staff/roles
```

Settings endpoints require `settings-manage`. Owners are expected to have this permission. Staff access depends on the assigned role.

---

## 5. Response Envelopes

### 5.1 Single resource

```ts
interface ApiEnvelope<T> {
  statusCode: number
  message: string
  item: T
}
```

Example:

```json
{
  "statusCode": 200,
  "message": "Campaign fetched successfully.",
  "item": {
    "id": "0198...",
    "title": "Community Health Program"
  }
}
```

### 5.2 Paginated list

```ts
interface PaginatedItem<T> {
  data: T[]
  total: number
  page: number
  perPage: number
}

interface PaginatedEnvelope<T> {
  statusCode: number
  message: string
  item: PaginatedItem<T>
}
```

Example:

```json
{
  "statusCode": 200,
  "message": "Campaigns fetched successfully.",
  "item": {
    "data": [],
    "total": 0,
    "page": 1,
    "perPage": 10
  }
}
```

### 5.3 Delete response

Successful deletion returns `200` with a null item:

```json
{
  "statusCode": 200,
  "message": "Data deleted successfully.",
  "item": null
}
```

### 5.4 Transitional legacy keys

During frontend migration, the backend MAY also return Laravel Resource keys such as `data`, `meta`, and `links` at the top level.

The `item` key is the stable contract. New frontend code MUST read from `item`.

Legacy keys will be removed in a future major API version.

---

## 6. Error Envelope

All errors use a problem-details-compatible shape:

```ts
interface ApiError {
  type: string
  title: string
  status: number
  traceId: string
  code?: string
  detail?: string
  errors?: Record<string, string[]>
}
```

Validation example:

```json
{
  "type": "https://jod.example/errors/validation",
  "title": "Validation failed",
  "status": 422,
  "traceId": "01J...",
  "code": "VALIDATION_FAILED",
  "errors": {
    "status": [
      "The selected status is invalid."
    ]
  }
}
```

### 6.1 Status code rules

| Status | Use |
|---:|---|
| `200` | Successful read, update, action, or delete. |
| `201` | Successful resource creation. |
| `400` | Malformed request that cannot be parsed. |
| `401` | Missing or invalid authentication. |
| `403` | Authenticated but not authorized. |
| `404` | Resource missing or outside the authenticated organization. |
| `409` | State or uniqueness conflict. |
| `422` | Validation or invalid lifecycle transition. |
| `500` | Unexpected server error. |

---

## 7. Common Field Conventions

### 7.1 Identifiers

All IDs are strings. UUID or ULID identifiers are recommended.

```ts
id: string
```

### 7.2 Date and time

Date-time response fields use ISO 8601 UTC strings:

```text
2026-07-28T13:45:30Z
```

Date-only request fields use:

```text
YYYY-MM-DD
```

### 7.3 Optional fields

Optional fields MAY be omitted or returned as `null` when not applicable. The behavior SHOULD remain consistent for a given resource.

### 7.4 Computed fields

The following fields are backend-computed and MUST NOT be accepted from create or update payloads:

- `raisedAmount`;
- `donorsCount`;
- `applicantsCount`;
- `viewsCount`;
- `reactionsCount`;
- `applicationsCount`.

---

## 8. List Query Parameters

All list endpoints support:

| Parameter | Type | Default | Description |
|---|---|---:|---|
| `page` | integer | `1` | One-based page number. |
| `perPage` | integer | `10` | Items per page; maximum `100`. |
| `sortingField` | string | resource-specific | Field from the endpoint allowlist. |
| `sortingDir` | `asc \| desc` | `desc` | Sort direction. |
| `searchQueries` | array | `[]` | Column-specific search expressions. |

Laravel-compatible encoding example:

```text
?searchQueries[0][columnName]=title&searchQueries[0][searchQuery]=health
```

Each endpoint MUST reject unsupported sorting or search columns with `422`.

Legacy `filter.*`, `sort`, and `sortBy` parameters MAY remain available during migration.

---

## 9. Endpoint Catalog

| Area | Method | Path | Access |
|---|---|---|---|
| Overview | `GET` | `/dashboard/overview` | Owner and permitted staff |
| Campaigns | `GET` | `/campaigns` | Owner and permitted staff |
| Campaigns | `GET` | `/campaigns/{id}` | Owner and permitted staff |
| Campaigns | `POST` | `/campaigns` | `campaigns-manage` |
| Campaigns | `PUT` | `/campaigns/{id}` | `campaigns-manage` |
| Campaigns | `DELETE` | `/campaigns/{id}` | `campaigns-manage` |
| Campaigns | `PATCH` | `/campaigns/{id}` | `campaigns-manage` |
| Donors | `GET` | `/donors?view=donors\|applicants` | `donors-view` or `donors-manage` |
| Donors | `GET` | `/donors/{id}` | `donors-view` or `donors-manage` |
| Donors | `POST` | `/donors?view=donors\|applicants` | `donors-manage` |
| Donors | `PUT` | `/donors/{id}` | `donors-manage` |
| Donors | `DELETE` | `/donors/{id}` | `donors-manage` |
| Posts | `GET` | `/posts` | Owner and permitted staff |
| Posts | `GET` | `/posts/{id}` | Owner and permitted staff |
| Posts | `POST` | `/posts` | `posts-manage` |
| Posts | `PUT` | `/posts/{id}` | `posts-manage` |
| Posts | `DELETE` | `/posts/{id}` | `posts-manage` |
| Posts | `PATCH` | `/posts/{id}/status` | `posts-manage` |
| Staff | `GET` | `/staff` | Owner only |
| Staff | `GET` | `/staff/{id}` | Owner only |
| Staff | `POST` | `/staff` | Owner only |
| Staff | `PUT` | `/staff/{id}` | Owner only |
| Staff | `DELETE` | `/staff/{id}` | Owner only |
| Roles | `GET` | `/staff/roles` | Owner only |
| Roles | `GET` | `/staff/roles/{id}` | Owner only |
| Roles | `POST` | `/staff/roles` | Owner only |
| Roles | `PUT` | `/staff/roles/{id}` | Owner only |
| Roles | `DELETE` | `/staff/roles/{id}` | Owner only |
| Permissions | `GET` | `/permissions/catalog` | Owner only |
| Notifications | `GET` | `/notifications` | `notifications-view` or manage |
| Notifications | `GET` | `/notifications/{id}` | `notifications-view` or manage |
| Notifications | `POST` | `/notifications` | `notifications-manage` |
| Notifications | `PATCH` | `/notifications/{id}/read` | `notifications-view` or manage |
| Notifications | `DELETE` | `/notifications/{id}` | `notifications-manage` |
| Reports | `GET` | `/reports` | `reports-view` |
| Reports | `GET` | `/reports/{id}` | `reports-view` |
| Reports | `PATCH` | `/reports/{id}/status` | `reports-view` |
| Audit log | `GET` | `/audit-log` | Owner and authorized staff |
| Audit log | `GET` | `/audit-log/{id}` | Owner and authorized staff |
| Profile | `GET` | `/profile` | Authenticated organization user |
| Profile | `PUT` | `/profile` | `settings-manage` |
| Settings | `GET` | `/settings` | Authenticated organization user |
| Settings | `PUT` | `/settings/account` | `settings-manage` |
| Settings | `PATCH` | `/settings/security` | `settings-manage` |
| Settings | `PUT` | `/settings/bank` | `settings-manage` |

---

## 10. Dashboard Overview

### 10.1 Endpoint

```http
GET /api/v1/org/dashboard/overview
```

### 10.2 Owner response models

```ts
interface OrgOwnerOverviewStat {
  id: 'campaigns' | 'posts' | 'donors' | 'staff' | 'notifications' | 'reports'
  label: string
  value: number
  hint: string
}

interface OrgOwnerActivityItem {
  id: string
  title: string
  detail: string
  category: 'campaigns' | 'posts' | 'donors' | 'staff' | 'reports' | 'general'
  priority: 'high' | 'medium' | 'low'
  at: string
}
```

### 10.3 Staff response models

```ts
interface OrgStaffOverviewStat {
  id: 'campaigns' | 'posts' | 'donors' | 'notifications'
  label: string
  value: number
  hint: string
}

interface OrgStaffActivityItem {
  id: string
  title: string
  detail: string
  category: 'campaigns' | 'posts' | 'donors' | 'reports' | 'tasks' | 'general'
  priority: 'high' | 'medium' | 'low'
  at: string
}
```

### 10.4 Response

```json
{
  "statusCode": 200,
  "message": "Dashboard overview fetched successfully.",
  "item": {
    "stats": [],
    "recentActivity": []
  }
}
```

The response shape is role-aware. Staff statistics MUST be limited to the staff member’s authorized organization scope.

---

## 11. Campaigns

### 11.1 Model

```ts
interface OrganizationCampaignItem {
  id: string
  title: string
  summary: string
  category: 'health' | 'education' | 'food' | 'shelter' | 'employment'
  status: 'draft' | 'active' | 'closed'
  location: string
  goalAmount: number
  raisedAmount: number
  beneficiariesCount: number
  donorsCount: number
  applicantsCount: number
  startDate: string
  endDate: string
  createdAt: string
  updatedAt: string
  closedAt?: string | null
  closedReason?: string | null
}
```

### 11.2 Create and update payload

```ts
interface CampaignFormValues {
  title?: string
  summary?: string
  category?: 'health' | 'education' | 'food' | 'shelter' | 'employment'
  status?: 'draft' | 'active' | 'closed'
  closedReason?: string | null
  location?: string
  goalAmount?: number
  beneficiariesCount?: number
  startDate?: string
  endDate?: string
}
```

### 11.3 List

```http
GET /api/v1/org/campaigns?status=all|draft|active|closed
```

Allowed sort fields:

```text
title
updatedAt
progress
startDate
endDate
```

Allowed search columns:

```text
title
summary
location
category
status
```

### 11.4 Update and status transitions

```http
PATCH /api/v1/org/campaigns/{id}
```

```json
{
  "status": "closed",
  "closedReason": "Campaign objectives were completed."
}
```

`status` is optional. When present, it uses the same lifecycle validation as the previous status update contract. `closedReason` may be sent when moving to `closed` and SHOULD be omitted for other target statuses.

Allowed transitions:

```text
draft -> active
active -> closed
```

Sending the current status is idempotent. Other transitions return `422`.

---

## 12. Donors and Applicants

The stable API exposes donors and applicants through one resource path. The `view` query parameter selects the record type.

### 12.1 Model

```ts
interface DonorEntryItem {
  id: string
  name: string
  email: string
  phone: string
  campaignTitle: string
  amountOrType: string
  donatedAt: string
  city?: string | null
  source?: string | null
  paymentMethod?: string | null
  campaignRef?: string | null
  assignedTo?: string | null
  internalNotes?: string | null
  requestType?: string | null
}
```

### 12.2 Create and update payload

```ts
interface DonorEntryFormValues {
  name: string
  email: string
  phone: string
  campaignTitle: string
  amountOrType: string
  donatedAt: string
  city: string
  source: string
  paymentMethod: string
  requestType: string
  assignedTo: string
  internalNotes: string
}
```

### 12.3 List

```http
GET /api/v1/org/donors?view=donors
GET /api/v1/org/donors?view=applicants
```

`view` is required and accepts only `donors` or `applicants`.

### 12.4 Create

```http
POST /api/v1/org/donors?view=donors
POST /api/v1/org/donors?view=applicants
```

For donor records:

- `paymentMethod` MAY be populated;
- `requestType` MUST be null or omitted.

For applicant records:

- `requestType` MAY be populated;
- `paymentMethod` MUST be null or omitted.

The referenced campaign and assigned staff member MUST belong to the authenticated organization.

---

## 13. Posts

### 13.1 Model

```ts
interface OrganizationPostItem {
  id: string
  title: string
  summary: string
  type:
    | 'general'
    | 'job_opportunity'
    | 'campaign_teaser'
    | 'campaign_update'
    | 'campaign_summary'
  status: 'draft' | 'published' | 'archived'
  authorName: string
  location: string
  campaignTitle?: string | null
  createdAt: string
  updatedAt: string
  publishedAt?: string | null
  viewsCount: number
  reactionsCount: number
  applicationsCount: number
}
```

### 13.2 Create and update payload

```ts
interface PostFormValues {
  title: string
  summary: string
  type:
    | 'general'
    | 'job_opportunity'
    | 'campaign_teaser'
    | 'campaign_update'
    | 'campaign_summary'
  status: 'draft' | 'published' | 'archived'
  authorName: string
  location: string
  campaignTitle: string
}
```

`campaignTitle` is required for campaign-related post types and MUST reference a campaign from the same organization.

### 13.3 List

```http
GET /api/v1/org/posts?status=all|draft|published|archived
```

Allowed sort fields:

```text
title
updatedAt
publishedAt
```

Allowed search columns:

```text
title
summary
authorName
location
type
status
campaignTitle
```

### 13.4 Status update

```http
PATCH /api/v1/org/posts/{id}/status
```

```json
{
  "status": "published"
}
```

Allowed transitions:

```text
draft -> published
published -> archived
archived -> draft
```

Sending the current status is idempotent. Other transitions return `422`.

---

## 14. Staff Members

Org Staff cannot access this resource.

### 14.1 Model

```ts
interface StaffMemberItem {
  id: string
  name: string
  email: string
  role: 'owner' | 'manager' | 'editor' | 'viewer'
  invitedAt: string
}
```

### 14.2 Create and update payload

```ts
interface StaffMemberFormValues {
  name: string
  email: string
  role: 'owner' | 'manager' | 'editor' | 'viewer'
}
```

### 14.3 Business rules

- The final active owner MUST NOT be removed or demoted.
- A staff member cannot be assigned a role from another organization.
- Duplicate active invitations SHOULD return `409`.
- Invitation delivery SHOULD be queued.

---

## 15. Staff Roles

Org Staff cannot access this resource.

### 15.1 Model

```ts
interface StaffRoleItem {
  id: string
  role: 'owner' | 'manager' | 'editor' | 'viewer'
  description: string
  permissions: string[]
  updatedAt: string
  isActive: boolean
  isSystem?: boolean
}
```

### 15.2 Create and update payload

```ts
interface StaffRoleFormValues {
  role: 'owner' | 'manager' | 'editor' | 'viewer'
  description: string
  permissions: string[]
  isActive: boolean
}
```

### 15.3 Business rules

- Every permission ID MUST exist in the permission catalog.
- A role with `isSystem=true` cannot be deleted.
- A role assigned to active staff cannot be deleted until assignments are migrated.
- Attempts to delete protected roles return `409`.

### 15.4 Permission catalog endpoint

```http
GET /api/v1/org/permissions/catalog
```

```ts
interface StaffPermissionOption {
  id: string
  label: string
  description: string
}
```

---

## 16. Notifications

### 16.1 Model

```ts
interface AdminNotificationItem {
  id: string
  mailbox: 'inbox' | 'sent'
  title: string
  body: string
  category: 'campaign' | 'post' | 'account' | 'report' | 'system'
  recipientScope: 'all' | 'users' | 'organizations'
  recipientLabel: string
  priority: 'normal' | 'high'
  status: 'unread' | 'read' | 'sent'
  createdAt: string
  sentAt: string
  readAt?: string | null
  referenceLabel: string
  referencePath: string
  createdBy: string
}
```

### 16.2 Create payload

```ts
interface CreateNotificationValues {
  title: string
  body: string
  category: 'campaign' | 'post' | 'account' | 'report' | 'system'
  recipientScope: 'all' | 'users' | 'organizations'
  recipientLabel: string
}
```

Optional backend extensions MAY include:

```ts
priority?: 'normal' | 'high'
referenceLabel?: string
referencePath?: string
```

### 16.3 List

```http
GET /api/v1/org/notifications?mailbox=inbox|sent
```

### 16.4 Mark as read

```http
PATCH /api/v1/org/notifications/{id}/read
```

This endpoint requires no request body. It is idempotent and sets:

```text
status = read
readAt = current UTC timestamp
```

Only inbox notifications can be marked as read.

---

## 17. Reports

Reports are externally submitted. Organization users cannot edit report content, but authorized users may update workflow status.

### 17.1 Model

```ts
interface OrgReportItem {
  id: string
  subject: string
  summary: string
  category: 'content' | 'harassment' | 'fraud' | 'other'
  status: 'open' | 'in_review' | 'closed'
  submittedAt: string
  reporterLabel: string
}
```

### 17.2 List

```http
GET /api/v1/org/reports?status=open|in_review|closed
```

### 17.3 Status update

```http
PATCH /api/v1/org/reports/{id}/status
```

```json
{
  "status": "in_review"
}
```

Allowed transitions:

```text
open -> in_review
open -> closed
in_review -> closed
```

Other transitions return `422`.

---

## 18. Audit Log

Audit-log records are system-generated and read-only.

### 18.1 Entry model

```ts
interface AuditLogEntry {
  id: string
  action: string
  user: string
  type: 'authentication' | 'moderation' | 'verification' | 'security' | 'content'
  reference?: string | null
  at: string
}
```

### 18.2 Summary model

```ts
interface AuditLogSummary {
  totalEntries: number
  totalUsers: number
  recentEntries: number
  latestTimestamp: string | null
}
```

### 18.3 List response

```json
{
  "statusCode": 200,
  "message": "Audit log fetched successfully.",
  "item": {
    "data": [],
    "total": 0,
    "page": 1,
    "perPage": 10,
    "summary": {
      "totalEntries": 0,
      "totalUsers": 0,
      "recentEntries": 0,
      "latestTimestamp": null
    }
  }
}
```

`recentEntries` counts records created in the previous 24 hours.

---

## 19. Profile

The same path is role-aware.

### 19.1 Model

```ts
interface OrganizationProfile {
  name: string
  email: string
  showVerifiedBadge: boolean
  showOrgBadge: boolean
}
```

### 19.2 Owner semantics

For Org Owner:

- `name` is the organization display name;
- `email` is the organization contact email;
- `showOrgBadge` reflects organization badge visibility.

### 19.3 Staff semantics

For Org Staff:

- `name` is the staff member display name;
- `email` is the staff member email;
- `showOrgBadge` is always `false`.

### 19.4 Endpoints

```http
GET /api/v1/org/profile
PUT /api/v1/org/profile
```

Update payload:

```json
{
  "name": "Organization Name",
  "email": "contact@example.com",
  "showVerifiedBadge": true,
  "showOrgBadge": true
}
```

The backend MAY ignore badge fields that the authenticated user is not allowed to change.

---

## 20. Settings

### 20.1 Model

```ts
interface OrganizationSettings {
  accountName: string
  accountEmail: string
  accountPhone: string
  recoveryEmail: string
  twoFactorEnabled: boolean
  bankName: string
  bankAccountNumber: string
  iban: string
}
```

### 20.2 Get settings

```http
GET /api/v1/org/settings
```

### 20.3 Update account

```http
PUT /api/v1/org/settings/account
```

```json
{
  "accountName": "JOD Organization",
  "accountEmail": "owner@example.com",
  "accountPhone": "+966500000000",
  "recoveryEmail": "recovery@example.com"
}
```

### 20.4 Update security

```http
PATCH /api/v1/org/settings/security
```

```json
{
  "twoFactorEnabled": true
}
```

The backend MAY require recent password confirmation or a second-factor challenge.

### 20.5 Update bank details

```http
PUT /api/v1/org/settings/bank
```

```json
{
  "bankName": "Example Bank",
  "bankAccountNumber": "1234567890",
  "iban": "SA0000000000000000000000"
}
```

Bank account numbers and IBAN values MUST be encrypted at rest and MUST NOT be included in logs or audit details.

---

## 21. Compatibility Aliases

The following aliases may remain available while clients migrate:

| Stable path | Legacy alias |
|---|---|
| `/dashboard/overview` | `/overview` |
| `PATCH /campaigns/{id}` with `status` | `POST /campaigns/{id}/close` for closing only |
| `PATCH /posts/{id}/status` | `POST /posts/{id}/publish` |
| `PATCH /posts/{id}/status` | `POST /posts/{id}/archive` |
| `PATCH /posts/{id}/status` | `POST /posts/{id}/restore` |
| `PATCH /notifications/{id}/read` | `PATCH /notifications/{id}/read-state` |
| `/profile` | `/settings/profile` |
| `/settings/bank` | `/settings/bank-account` |
| `/staff/roles` | `/roles` |
| `/donors?view=applicants` | `/applicants` |

Legacy aliases MUST follow the same authentication, organization-scoping, and authorization rules as the stable endpoint.

---

## 22. Implementation Status

This section is informational and does not change the target contract.

### Available or aliased in `agent/org-api-contract-foundation`

- response `statusCode`, `message`, and `item` compatibility envelope;
- `/dashboard/overview`;
- campaign CRUD with status transitions on `/campaigns/{id}`;
- post CRUD and `/posts/{id}/status`;
- donor CRUD through the legacy donor resource;
- applicant CRUD through the legacy applicant resource;
- notifications and `/notifications/{id}/read`;
- `/profile` alias;
- staff CRUD;
- `/staff/roles` alias;
- permission catalog.

### Required follow-up implementation

- unified donor/applicant facade using `/donors?view=...`;
- report status update;
- organization audit-log endpoints and summary;
- consolidated settings endpoints;
- complete `searchQueries` support and endpoint allowlists;
- full owner/staff authorization matrix tests;
- removal schedule for legacy response and route aliases.

---

## 23. Contract Testing Requirements

Every endpoint MUST have feature tests for:

1. unauthenticated access;
2. authorized owner access;
3. authorized staff access where applicable;
4. staff access without required permission;
5. cross-organization resource isolation;
6. exact success envelope;
7. exact validation/error envelope;
8. pagination defaults and limits;
9. allowed and rejected sorting fields;
10. allowed and rejected search fields;
11. lifecycle transition success and failure;
12. computed-field input rejection or omission;
13. delete response behavior.

The generated Scramble/OpenAPI document SHOULD be exported and reviewed whenever this contract changes.

```bash
composer test
composer api-docs
```

---

## 24. Change Management

Backward-compatible additions MAY be released within API version `v1`.

The following require a new major API version or a documented migration window:

- removing response fields;
- renaming response fields;
- changing field types;
- removing enum values;
- making optional fields required;
- changing lifecycle rules in a way that rejects previously valid requests;
- removing compatibility aliases.

Contract changes MUST update:

- this document;
- feature tests;
- Scramble/OpenAPI output;
- frontend TypeScript types where applicable.
