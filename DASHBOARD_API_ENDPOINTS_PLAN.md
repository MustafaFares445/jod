# Dashboard Backend API Endpoints Plan

## 1) Current state (verified)

- Frontend dashboard pages are fully mock/static-data driven under:
  - `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\...`
- Backend currently has **no API routes**:
  - `php artisan route:list --path=api --json` returns no matches.
- Existing routes are only web/root/health/vendor helpers.

This means the plan below is a **full initial API blueprint** for dashboard integration.

---

## 2) API architecture decisions

### 2.1 Prefixes and role scopes

- Base prefix: `/api/v1`
- Auth: `auth:sanctum` on all dashboard routes.
- Route groups:
  - `/api/v1/admin/...` for admin dashboard.
  - `/api/v1/org/...` for organization owner/staff dashboard.
  - `/api/v1/me/...` for profile + permission bootstrap.

### 2.2 Response contract

- Collections:
  - `data: []`
  - `meta: { currentPage, perPage, total, lastPage }`
  - `message`
- Single resource:
  - `data: {}`
  - `message`
- Mutations:
  - `data` updated resource (or `204` for delete)
  - `message`

### 2.3 Query conventions (frontend-compatible)

- Pagination:
  - `page`, `perPage` (default `20`, max `100`)
- Filters:
  - `filter.<field>`
- Sort:
  - `sort` (spatie style: `field` / `-field`)
- Compatibility:
  - Accept frontend `sortBy` keys initially, map server-side to `sort`.
  - Accept `"all"` for filters and ignore it.

### 2.4 Permissions (suggested groups)

- `dashboard.view`
- `users.view|create|update|delete|reset_password`
- `organizations.view|update|delete|verify|accept`
- `posts.review.view|approve|reject`
- `campaigns.review.view|approve|reject`
- `reports.view|claim|request_info|close`
- `notifications.view|create|update|delete|resend`
- `badges.view|create|update|delete`
- `articles.view|create|update|delete|publish`
- `analytics.view`
- `audit_logs.view`
- `platform_settings.view|update`
- `org.campaigns.*`, `org.posts.*`, `org.donors.*`, `org.applicants.*`, `org.staff.*`, `org.roles.*`, `org.notifications.*`, `org.reports.view`, `org.settings.update`

---

## 3) Endpoint catalog

## 3.1 Bootstrap (`/api/v1/me`)

### `GET /api/v1/me`
- Purpose: topbar/profile bootstrap.
- Response fields:
  - `id, name, email, phone, userType, organizationId?, organizationName?, status, createdAt, lastActiveAt`

### `GET /api/v1/me/permissions`
- Purpose: sidebar/menu gating and action buttons.
- Response fields:
  - `modules[]`, `flat{ permissionName: boolean }`, `granted[]`

### `GET /api/v1/me/dashboard-context`
- Purpose: one-call initialization for dashboard.
- Response fields:
  - `profile`
  - `permissions`
  - `counters` (unread notifications, pending reviews, open reports)

---

## 3.2 Admin overview / analytics / audit

### `GET /api/v1/admin/overview`
- Purpose: admin KPI cards + recent activity feed.
- Response:
  - `stats[]` with `{ id, label, value, subLabel, icon }`
  - `activity[]` with `{ id, title, detail, at }`

### `GET /api/v1/admin/analytics/kpis`
- Query:
  - `range` (`7d|30d|90d|12m`)
- Response:
  - `kpis[]` with `{ id, label, value, changeVsLastMonth }`

### `GET /api/v1/admin/analytics/weekly`
- Query:
  - `range`
- Response:
  - `rows[]` with `{ weekLabel, visits, newUsers, donations }`

### `GET /api/v1/admin/audit-logs`
- Query:
  - `page, perPage`
  - `filter.actorUserId`
  - `filter.action`
  - `filter.from`
  - `filter.to`
  - `sort` (`at|-at`)
- Response fields:
  - `{ id, action, user, at, entityType?, entityId?, metadata? }`

---

## 3.3 Admin users

### `GET /api/v1/admin/users`
- Query:
  - `page, perPage`
  - `filter.status` (`active|inactive`)
  - `filter.role` (`general|volunteer|job_seeker|donor`)
  - `filter.search`
  - `sort` (`createdAt|-createdAt|name|-name|lastActiveAt|-lastActiveAt`)
- Response fields:
  - `{ id, name, email, phone, role, status, postsCount, reportsCount, createdAt, lastActiveAt }`

### `POST /api/v1/admin/users`
- Body:
  - `name, email, phone, role, status`
  - optional `password` (if not provided, server generates invite/reset flow)

### `GET /api/v1/admin/users/{userId}`

### `PATCH /api/v1/admin/users/{userId}`
- Body:
  - `name, email, phone, role, status`

### `PATCH /api/v1/admin/users/{userId}/status`
- Body:
  - `status` (`active|inactive`)

### `PATCH /api/v1/admin/users/{userId}/password`
- Body:
  - `newPassword` (min 8)
  - `confirmPassword` (must match)

### `DELETE /api/v1/admin/users/{userId}`
- Soft delete.

---

## 3.4 Admin organizations

### `GET /api/v1/admin/organizations`
- Query:
  - `page, perPage`
  - `filter.verificationStatus` (`verified|unverified`)
  - `filter.status` (`active|inactive`)
  - `filter.location`
  - `filter.search`
  - `sort` (`name|-name|createdAt|-createdAt`)
  - compatibility: `sortBy=name_asc|name_desc|created_newest|created_oldest`
- Response fields (list):
  - `{ id, name, email, phone, location, verificationStatus, status, campaignsCount, postsCount, activeVolunteersCount, activityScore, createdAt, lastActiveAt }`

### `GET /api/v1/admin/organizations/{organizationId}`
- Response fields (details):
  - list fields +
  - `organizationType, registrationNumber, establishmentDate, shortAddress, description, licenseDocumentName, delegationDocumentName, ownerFullName, ownerEmail, ownerPhone, website?, socialMedia?, acceptedAt?`

### `PATCH /api/v1/admin/organizations/{organizationId}`
- Body:
  - editable profile fields from details form.

### `PATCH /api/v1/admin/organizations/{organizationId}/status`
- Body:
  - `status` (`active|inactive`)

### `PATCH /api/v1/admin/organizations/{organizationId}/verification`
- Body:
  - `verificationStatus` (`verified|unverified`)

### `POST /api/v1/admin/organizations/{organizationId}/accept`
- Behavior:
  - set `status=active`, `verificationStatus=verified`, `acceptedAt=now`

### `DELETE /api/v1/admin/organizations/{organizationId}`
- Soft delete.

---

## 3.5 Admin post moderation

### `GET /api/v1/admin/review/posts`
- Query:
  - `page, perPage`
  - `filter.status` (`pending|approved|rejected`)
  - `filter.organizationId` (preferred)
  - compatibility: `filter.organizationName`
  - `filter.type` (`help_request|job_opportunity|awareness|campaign_update`)
  - `sort` (`title|-title|submittedAt|-submittedAt`)
  - compatibility: `sortBy=title_asc|title_desc|created_at_newest|created_at_oldest`
- Response fields:
  - `{ id, title, summary, organizationName, authorName, location, submittedAt, publishedAt, status, type, reviewedBy?, rejectionReason? }`

### `GET /api/v1/admin/review/posts/{postId}`

### `POST /api/v1/admin/review/posts/{postId}/approve`
- Body optional:
  - `note?`

### `POST /api/v1/admin/review/posts/{postId}/reject`
- Body:
  - `reason` (required, min 8)

---

## 3.6 Admin campaign moderation

### `GET /api/v1/admin/review/campaigns`
- Query:
  - `page, perPage`
  - `filter.status` (`pending|approved|rejected`)
  - `filter.organizationId` (preferred)
  - compatibility: `filter.organizationName`
  - `filter.category` (`health|education|shelter|food|emergency`)
  - `sort` (`title|-title|submittedAt|-submittedAt`)
  - compatibility: `sortBy=title_asc|title_desc|created_at_newest|created_at_oldest`
- Response fields:
  - `{ id, title, summary, organizationName, managerName, location, category, goalAmount, raisedAmount, beneficiariesCount, startDate, endDate, submittedAt, status, reviewedBy?, rejectionReason? }`

### `GET /api/v1/admin/review/campaigns/{campaignId}`

### `POST /api/v1/admin/review/campaigns/{campaignId}/approve`

### `POST /api/v1/admin/review/campaigns/{campaignId}/reject`
- Body:
  - `reason` (required, min 8)

---

## 3.7 Admin reports management

### `GET /api/v1/admin/reports`
- Query:
  - `page, perPage`
  - `filter.status` (`new|in_progress|waiting_response|closed`)
  - `filter.severity` (`low|medium|high|critical`)
  - `filter.entityType` (`post|campaign|user|organization`)
  - `sort` (`createdAt|-createdAt`)
- Response fields:
  - `{ id, title, description, status, severity, entityType, entityId, organizationName, reporterName, createdAt, assignee?, timeline[], evidence[] }`

### `GET /api/v1/admin/reports/{reportId}`

### `POST /api/v1/admin/reports/{reportId}/claim`
- Body optional:
  - `assigneeId?`
- Transition:
  - `new -> in_progress`

### `POST /api/v1/admin/reports/{reportId}/request-info`
- Body:
  - `note?`
- Transition:
  - `in_progress -> waiting_response`

### `POST /api/v1/admin/reports/{reportId}/close`
- Body:
  - `note?`
- Transition:
  - `in_progress|waiting_response -> closed`

Validation:
- illegal transitions return `422`.

---

## 3.8 Admin notifications

### `GET /api/v1/admin/notifications`
- Query:
  - `page, perPage`
  - `filter.mailbox` (`inbox|sent`)
  - `filter.status` (`unread|read|sent`)
  - `filter.category` (`campaign|post|account|report|system`)
  - `filter.recipientScope` (`all|users|organizations`)
  - `filter.date` (`all|today|last_7_days`)
  - `sort` (`sentAt|-sentAt`)
- Response fields:
  - `{ id, mailbox, title, body, category, recipientScope, recipientLabel, priority, status, createdAt, sentAt, readAt?, referenceLabel, referencePath, createdBy }`

### `GET /api/v1/admin/notifications/{notificationId}`

### `POST /api/v1/admin/notifications`
- Body:
  - `title, body, category, recipientScope, recipientLabel`
  - optional `priority` (`normal|high`)

### `PATCH /api/v1/admin/notifications/{notificationId}/read-state`
- Body:
  - `status` (`read|unread`)

### `POST /api/v1/admin/notifications/{notificationId}/resend`

### `DELETE /api/v1/admin/notifications/{notificationId}`

---

## 3.9 Admin rewards (badges)

### `GET /api/v1/admin/badges`
- Query:
  - `page, perPage`
  - `filter.isActive`
  - `filter.search`
  - `sort` (`createdAt|-createdAt|name|-name`)
- Response fields:
  - `{ id, name, description, criteria, iconName, isActive, createdAt }`

### `POST /api/v1/admin/badges`
- Body:
  - `name, description, criteria, iconName, isActive`

### `GET /api/v1/admin/badges/{badgeId}`

### `PATCH /api/v1/admin/badges/{badgeId}`

### `PATCH /api/v1/admin/badges/{badgeId}/status`
- Body:
  - `isActive`

### `DELETE /api/v1/admin/badges/{badgeId}`

---

## 3.10 Admin content (articles)

### `GET /api/v1/admin/articles`
- Query:
  - `page, perPage`
  - `filter.status` (`draft|published`)
  - `filter.search`
  - `sort` (`createdAt|-createdAt|publishedAt|-publishedAt|title|-title`)
- Response fields:
  - `{ id, title, slug, excerpt, status, publishedAt?, createdAt, authorName }`

### `POST /api/v1/admin/articles`
- Body:
  - `title, slug?, excerpt, authorName, status`
- Behavior:
  - if `slug` empty -> server slugify title.

### `GET /api/v1/admin/articles/{articleId}`

### `PATCH /api/v1/admin/articles/{articleId}`
- Body:
  - `title, slug, excerpt, authorName, status`

### `DELETE /api/v1/admin/articles/{articleId}`

---

## 3.11 Admin platform settings

### `GET /api/v1/admin/platform-settings`
- Response:
  - `siteName, allowNewPosts, requirePostReview`

### `PATCH /api/v1/admin/platform-settings`
- Body:
  - `siteName, allowNewPosts, requirePostReview`

---

## 3.12 Organization overview

### `GET /api/v1/org/overview`
- Query:
  - `view` (`owner|staff`) optional
- Response:
  - `stats[]` with `{ id, label, value, hint }`
  - `activity[]` with `{ id, title, detail, at }`

---

## 3.13 Organization campaigns (`owner + staff with permission`)

### `GET /api/v1/org/campaigns`
- Query:
  - `page, perPage`
  - `filter.status` (`draft|active|closed`)
  - `filter.category` (`health|education|food|shelter|employment`)
  - `filter.location`
  - `sort` (`updatedAt|-updatedAt|progress|-progress`)
  - compatibility: `sortBy=updated_newest|updated_oldest|progress_highest|progress_lowest`
- Response fields:
  - `{ id, title, summary, category, status, location, goalAmount, raisedAmount, beneficiariesCount, donorsCount, applicantsCount, startDate, endDate, createdAt, updatedAt, closedAt?, closedReason? }`

### `POST /api/v1/org/campaigns`
- Body:
  - `title, summary, category, status, location, goalAmount, beneficiariesCount, startDate, endDate`

### `GET /api/v1/org/campaigns/{campaignId}`

### `PATCH /api/v1/org/campaigns/{campaignId}`

### `POST /api/v1/org/campaigns/{campaignId}/close`
- Body:
  - `reason` (required, min 8)
- Transition:
  - `active -> closed`

### `DELETE /api/v1/org/campaigns/{campaignId}`

---

## 3.14 Organization posts

### `GET /api/v1/org/posts`
- Query:
  - `page, perPage`
  - `filter.status` (`draft|published|archived`)
  - `filter.type` (`general|job_opportunity|campaign_teaser|campaign_update|campaign_summary`)
  - `sort` (`updatedAt|-updatedAt|title|-title`)
  - compatibility: `sortBy=updated_newest|updated_oldest|title_asc|title_desc`
- Response fields:
  - `{ id, title, summary, type, status, authorName, location, campaignTitle?, createdAt, updatedAt, publishedAt?, viewsCount, reactionsCount, applicationsCount }`

### `POST /api/v1/org/posts`
- Body:
  - `title, summary, type, status, authorName, location, campaignTitle?`
- Validation:
  - campaign-related types require `campaignTitle` or `campaignId`.

### `GET /api/v1/org/posts/{postId}`

### `PATCH /api/v1/org/posts/{postId}`

### `POST /api/v1/org/posts/{postId}/publish`
- Transition:
  - `draft -> published`

### `POST /api/v1/org/posts/{postId}/archive`
- Transition:
  - `published -> archived`

### `POST /api/v1/org/posts/{postId}/restore`
- Transition:
  - `archived -> draft`

### `DELETE /api/v1/org/posts/{postId}`

---

## 3.15 Organization donors and applicants

Use separate resources matching real tables (`donations`, `campaign_applications`) and keep UI merge in frontend.

### `GET /api/v1/org/donors`
- Query:
  - `page, perPage`
  - `filter.campaignId`
  - `filter.city`
  - `filter.search`
  - `sort` (`donatedAt|-donatedAt|name|-name`)
  - compatibility: `sortBy=date_newest|date_oldest|name_asc|name_desc`
- Response fields:
  - `{ id, name, email, phone, campaignTitle, amountOrType, donatedAt, city?, source?, paymentMethod?, campaignRef?, assignedTo?, internalNotes? }`

### `POST /api/v1/org/donors`

### `PATCH /api/v1/org/donors/{donorId}`

### `DELETE /api/v1/org/donors/{donorId}`

### `GET /api/v1/org/applicants`
- Query:
  - `page, perPage`
  - `filter.campaignId`
  - `filter.applicantStatus` (maps to current UI `amountOrType`)
  - `filter.search`
  - `sort` same as donors.
- Response fields:
  - same shape used by table for now.

### `POST /api/v1/org/applicants`

### `PATCH /api/v1/org/applicants/{applicantId}`

### `DELETE /api/v1/org/applicants/{applicantId}`

---

## 3.16 Organization staff and roles

### `GET /api/v1/org/staff`
- Query:
  - `page, perPage`
  - `filter.role` (`owner|manager|editor|viewer`)
  - `sort` (`invitedAt|-invitedAt|name|-name`)
  - compatibility: `sortBy=invited_newest|invited_oldest|name_asc|name_desc`
- Response fields:
  - `{ id, name, email, role, invitedAt }`

### `POST /api/v1/org/staff`
- Body:
  - `name, email, role`

### `PATCH /api/v1/org/staff/{staffId}`

### `DELETE /api/v1/org/staff/{staffId}`

### `GET /api/v1/org/roles`
- Query:
  - `page, perPage`
  - `filter.status` (`active|inactive`)
  - `sort` (`updatedAt|-updatedAt|permissionsCount|-permissionsCount|membersCount|-membersCount`)
  - compatibility: `sortBy=updated_newest|updated_oldest|permissions_most|members_most`
- Response fields:
  - `{ id, role, description, permissions[], updatedAt, isActive, isSystem?, membersCount }`

### `POST /api/v1/org/roles`
- Body:
  - `role, description, permissions[], isActive`

### `PATCH /api/v1/org/roles/{roleId}`
- Body:
  - `description, permissions[], isActive`

### `DELETE /api/v1/org/roles/{roleId}`
- Rules:
  - reject delete for `isSystem=true`
  - reassign affected members to `viewer`

### `GET /api/v1/org/permissions/catalog`
- Purpose:
  - source of selectable permissions for roles form.

---

## 3.17 Organization notifications and reports

### `GET /api/v1/org/notifications`
- Query:
  - same as admin notifications, scoped to current organization.

### `POST /api/v1/org/notifications`
- Optional by permission.

### `PATCH /api/v1/org/notifications/{notificationId}/read-state`

### `POST /api/v1/org/notifications/{notificationId}/resend`

### `DELETE /api/v1/org/notifications/{notificationId}`

### `GET /api/v1/org/reports`
- Query:
  - `page, perPage`
  - `filter.status` (`open|in_review|closed`)
  - `filter.category` (`content|harassment|fraud|other`)
  - `sort` (`submittedAt|-submittedAt`)
- Response fields:
  - `{ id, subject, summary, category, status, submittedAt, reporterLabel }`

### `GET /api/v1/org/reports/{reportId}`

---

## 3.18 Profile and org settings

### `GET /api/v1/me/profile`
- Response:
  - `name, email, phone?, verifiedFlags...`

### `PATCH /api/v1/me/profile`
- Body:
  - `name, email`

### `GET /api/v1/org/settings/bank-account`
- Response:
  - `bankName, iban`

### `PATCH /api/v1/org/settings/bank-account`
- Body:
  - `bankName, iban`

---

## 4) Sort/filter compatibility map from frontend

## 4.1 Admin moderation
- `created_at_newest` -> `sort=-submittedAt`
- `created_at_oldest` -> `sort=submittedAt`
- `title_asc` -> `sort=title`
- `title_desc` -> `sort=-title`

## 4.2 Organizations
- `name_asc` -> `sort=name`
- `name_desc` -> `sort=-name`
- `created_newest` -> `sort=-createdAt`
- `created_oldest` -> `sort=createdAt`

## 4.3 Org campaigns
- `updated_newest` -> `sort=-updatedAt`
- `updated_oldest` -> `sort=updatedAt`
- `progress_highest` -> `sort=-progress`
- `progress_lowest` -> `sort=progress`

## 4.4 Org posts
- `updated_newest` -> `sort=-updatedAt`
- `updated_oldest` -> `sort=updatedAt`
- `title_asc` -> `sort=title`
- `title_desc` -> `sort=-title`

## 4.5 Donors/applicants
- `date_newest` -> `sort=-donatedAt`
- `date_oldest` -> `sort=donatedAt`
- `name_asc` -> `sort=name`
- `name_desc` -> `sort=-name`

## 4.6 Staff/roles
- `invited_newest` -> `sort=-invitedAt`
- `invited_oldest` -> `sort=invitedAt`
- `updated_newest` -> `sort=-updatedAt`
- `updated_oldest` -> `sort=updatedAt`
- `permissions_most` -> `sort=-permissionsCount`
- `members_most` -> `sort=-membersCount`

---

## 5) Validation rules that must exist (from UI behavior)

- Reject reason for post/campaign moderation: min length `8`.
- Close campaign reason: min length `8`.
- Change password:
  - min length `8`
  - `confirmPassword` must match.
- Dates:
  - `startDate <= endDate` for campaigns.
- Numeric:
  - `goalAmount >= 0`, `beneficiariesCount >= 0`.
- Enum strictness:
  - all status/type/category/scope values above validated with `Rule::in(...)`.

---

## 6) Implementation phases (recommended)

## Phase 1 (critical dashboard unblock)
- `/me`, `/me/permissions`
- admin users
- admin organizations + details + accept/verify/status
- admin post/campaign moderation + approve/reject
- admin reports + state transitions
- admin notifications

## Phase 2 (admin secondary)
- admin badges
- admin articles (replace localStorage flow)
- admin overview + analytics + audit logs
- admin platform settings

## Phase 3 (organization workspace)
- org campaigns
- org posts + workflow transitions
- org donors/applicants
- org notifications
- org reports (read)
- org profile/settings

## Phase 4 (advanced permissions/governance)
- org staff + roles with permission catalog integration
- stricter policy coverage
- audit events for each state transition

---

## 7) Backend file layout to implement (per your API standards)

- Routes:
  - `routes/api.php`
- Controllers:
  - `app/Http/Controllers/API/Admin/*`
  - `app/Http/Controllers/API/Org/*`
  - `app/Http/Controllers/API/Me/*`
- Requests:
  - `app/Http/Requests/*`
- Data DTOs:
  - `app/Data/*`
- Services:
  - `app/Services/*`
- Resources:
  - `app/Http/Resources/*`
- Filter traits:
  - `app/Traits/FilterQueries/*`
- Policies + permissions seed:
  - `app/Policies/*`
  - `database/seeders/Permissions/*`
- Feature tests:
  - `tests/Feature/Admin/*`
  - `tests/Feature/Org/*`
  - `tests/Feature/Me/*`

---

## 8) Frontend files used to derive this plan

- `C:\laragon\www\JOD\JOD-FrontEnd\src\constant\routes.ts`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\users-management\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\organizations-management\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\posts-review\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\campaigns-review\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\reports-management\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\notifications-management\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\content-management\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\rewards-management\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\organization-campaigns\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\organization-posts-management\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\donors-management\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\staff-management\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\admin-overview\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\analytics-dashboard\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\audit-log\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\dashboard-profile\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\dashboard-settings\*`
- `C:\laragon\www\JOD\JOD-FrontEnd\src\components\pages\platform-settings\*`

