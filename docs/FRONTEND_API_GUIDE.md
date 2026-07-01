# JOD Frontend API Guide

**Version:** 1.1  
**Base URL:** `http://localhost/api/v1`  
**Auth:** Laravel Sanctum bearer token  
**Interactive docs:** `/docs/api`  
**OpenAPI JSON:** `/docs/api.json`  
**Static export:** `composer api-docs` writes `public/api.json`

---

## 1. Scramble / OpenAPI Docs

Scramble is installed and configured to generate docs for `/api/v1/*` routes.

Frontend team should use:

| Resource | URL / Command | Usage |
|---|---|---|
| Live docs UI | `/docs/api` | Human-readable API guide and Try It UI. |
| Live OpenAPI JSON | `/docs/api.json` | Import into Postman/OpenAPI tooling. |
| Static OpenAPI JSON | `composer api-docs` | Exports `public/api.json`. |

Local usage:

```bash
composer install
php artisan optimize:clear
php artisan serve
```

Open:

```text
http://localhost:8000/docs/api
```

For staging access, set:

```env
SCRAMBLE_ALLOW_PRODUCTION_DOCS=true
```

Keep it `false` on public production unless docs are intentionally public.

---

## 2. Request Rules

All endpoints are prefixed with:

```text
/api/v1
```

Required headers for authenticated requests:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

`Authorization` is not required only for:

```http
POST /auth/login
```

Frontend should use camelCase request and response fields.

List endpoints should use:

| Query | Example | Notes |
|---|---|---|
| `page` | `page=1` | Current page. |
| `perPage` | `perPage=20` | Page size. |
| `filter.search` | `filter.search=ahmad` | Search text. |
| `filter.status` | `filter.status=active` | Status dropdown. |
| `sort` | `sort=-createdAt` | Prefix `-` for descending. |

---

## 3. Response / Error Contract

### Single resource

```json
{
  "data": {
    "id": "uuid",
    "name": "Example"
  },
  "message": "Optional message"
}
```

### Collection

Laravel resource collections return:

```json
{
  "data": [],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 0
  }
}
```

### Delete

```http
204 No Content
```

### Validation error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Frontend should show `message` as a toast and map `errors[field][0]` to form inputs.

### Auth error

```json
{
  "message": "Unauthenticated."
}
```

Frontend should clear token and redirect to login on `401`.

---

## 4. Authentication

### Login

```http
POST /auth/login
```

Request:

```json
{
  "email": "admin@jod.com",
  "password": "password"
}
```

Response:

```json
{
  "data": {
    "token": "1|token",
    "tokenType": "Bearer",
    "user": {
      "id": "uuid",
      "name": "John Admin",
      "email": "admin@jod.com",
      "phone": "+962790000000",
      "userType": "admin",
      "status": "active",
      "organizationId": null,
      "postsCount": 0,
      "reportsCount": 0,
      "createdAt": "2026-07-01T12:00:00+00:00",
      "updatedAt": "2026-07-01T12:00:00+00:00",
      "lastActiveAt": "2026-07-01T12:00:00+00:00"
    }
  },
  "message": "Logged in successfully"
}
```

### Logout

```http
POST /auth/logout
```

Response:

```json
{
  "message": "Logged out successfully"
}
```

---

## 5. Bootstrap Flow

After login or page refresh:

1. `GET /me` — current profile/topbar.
2. `GET /me/permissions` — menus, tabs, and action buttons.
3. `GET /me/dashboard-context` — profile + permissions + counters.
4. Admin workspace: `GET /admin/overview`.
5. Organization workspace: `GET /org/overview`.

### Me endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/me` | Current user profile. |
| PATCH | `/me/profile` | Update current user profile. |
| GET | `/me/permissions` | Current user permission catalog. |
| GET | `/me/dashboard-context` | One-call dashboard bootstrap. |

User fields:

```json
{
  "id": "uuid",
  "name": "John Admin",
  "email": "admin@jod.com",
  "phone": "+962790000000",
  "userType": "admin",
  "status": "active",
  "organizationId": "uuid-or-null",
  "postsCount": 0,
  "reportsCount": 0,
  "createdAt": "2026-07-01T12:00:00+00:00",
  "updatedAt": "2026-07-01T12:00:00+00:00",
  "lastActiveAt": "2026-07-01T12:00:00+00:00"
}
```

---

## 6. Admin API Catalog

### Overview / analytics / audit

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/admin/overview` | KPI cards and activity feed. |
| GET | `/admin/analytics/kpis` | Analytics KPI cards. |
| GET | `/admin/analytics/weekly` | Weekly chart rows. |
| GET | `/admin/audit-logs` | Audit log table. |

Suggested analytics query:

```http
GET /admin/analytics/kpis?range=30d
GET /admin/analytics/weekly?range=30d
```

### Users

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/admin/users` | List users. |
| POST | `/admin/users` | Create user. |
| GET | `/admin/users/{user}` | User details. |
| PATCH/PUT | `/admin/users/{user}` | Update user. |
| DELETE | `/admin/users/{user}` | Delete user. |
| PATCH | `/admin/users/{user}/status` | Change status. |
| PATCH | `/admin/users/{user}/password` | Change password. |

### Organizations

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/admin/organizations` | List organizations. |
| POST | `/admin/organizations` | Create organization. |
| GET | `/admin/organizations/{organization}` | Details. |
| PATCH/PUT | `/admin/organizations/{organization}` | Update. |
| DELETE | `/admin/organizations/{organization}` | Delete. |
| PATCH | `/admin/organizations/{organization}/status` | Change active status. |
| PATCH | `/admin/organizations/{organization}/verification` | Change verification status. |
| POST | `/admin/organizations/{organization}/accept` | Accept organization. |

Organization fields include:

```json
{
  "id": "uuid",
  "name": "Good Org",
  "email": "org@example.com",
  "phone": "+962790000000",
  "location": "Amman",
  "verificationStatus": "verified",
  "status": "active",
  "campaignsCount": 3,
  "postsCount": 12,
  "activeVolunteersCount": 20,
  "activityScore": 80.5,
  "organizationType": "charity",
  "registrationNumber": "REG-001",
  "establishmentDate": "2024-01-01",
  "shortAddress": "Amman",
  "description": "Organization description",
  "ownerFullName": "Owner Name",
  "ownerEmail": "owner@example.com",
  "ownerPhone": "+962790000000",
  "website": "https://example.com",
  "socialMedia": [],
  "bankName": "Bank Name",
  "iban": "JO00BANK0000000000000000000000",
  "acceptedAt": "2026-07-01T12:00:00+00:00"
}
```

### Review queue

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/admin/review/posts` | List post reviews. |
| GET | `/admin/review/posts/{post}` | Show post review. |
| POST | `/admin/review/posts/{post}/approve` | Approve post. |
| POST | `/admin/review/posts/{post}/reject` | Reject post. |
| GET | `/admin/review/campaigns` | List campaign reviews. |
| GET | `/admin/review/campaigns/{campaign}` | Show campaign review. |
| POST | `/admin/review/campaigns/{campaign}/approve` | Approve campaign. |
| POST | `/admin/review/campaigns/{campaign}/reject` | Reject campaign. |

Reject example:

```json
{
  "reason": "Missing required documents"
}
```

### Reports

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/admin/reports` | List reports. |
| GET | `/admin/reports/{report}` | Report details. |
| POST | `/admin/reports/{report}/claim` | Assign current admin. |
| POST | `/admin/reports/{report}/request-info` | Request more info. |
| POST | `/admin/reports/{report}/close` | Close report. |

### Notifications

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/admin/notifications` | List notifications. |
| POST | `/admin/notifications` | Create notification. |
| GET | `/admin/notifications/{notification}` | Show notification. |
| PATCH/PUT | `/admin/notifications/{notification}` | Update notification. |
| DELETE | `/admin/notifications/{notification}` | Delete notification. |
| PATCH | `/admin/notifications/{notification}/read-state` | Mark read/unread. |
| POST | `/admin/notifications/{notification}/resend` | Resend notification. |

### Content and settings

| Method | Endpoint | Purpose |
|---|---|---|
| GET/POST | `/admin/badges` | List/create badges. |
| GET/PATCH/DELETE | `/admin/badges/{badge}` | Show/update/delete badge. |
| PATCH | `/admin/badges/{badge}/status` | Activate/deactivate badge. |
| GET/POST | `/admin/articles` | List/create articles. |
| GET/PATCH/DELETE | `/admin/articles/{article}` | Show/update/delete article. |
| GET/POST | `/admin/categories` | List/create categories. |
| GET/PATCH/DELETE | `/admin/categories/{category}` | Show/update/delete category. |
| PATCH | `/admin/categories/{category}/status` | Activate/deactivate category. |
| GET | `/admin/platform-settings` | Read settings. |
| PATCH | `/admin/platform-settings` | Update settings. |

---

## 7. Organization Workspace API Catalog

### Overview

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/org/overview` | Organization KPI cards and activity. |

### Campaigns

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/org/campaigns` | List campaigns. |
| POST | `/org/campaigns` | Create campaign. |
| GET | `/org/campaigns/{campaign}` | Campaign details. |
| PATCH/PUT | `/org/campaigns/{campaign}` | Update campaign. |
| DELETE | `/org/campaigns/{campaign}` | Delete campaign. |
| POST | `/org/campaigns/{campaign}/close` | Close campaign. |

Campaign fields include `id`, `title`, `summary`, `category`, `status`, `organizationName`, `managerName`, `location`, `goalAmount`, `raisedAmount`, `beneficiariesCount`, `donorsCount`, `applicantsCount`, `startDate`, `endDate`, `submittedAt`, `createdAt`, `updatedAt`, `closedAt`, `closedReason`, `reviewedBy`, and `rejectionReason`.

### Posts

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/org/posts` | List posts. |
| POST | `/org/posts` | Create post. |
| GET | `/org/posts/{post}` | Post details. |
| PATCH/PUT | `/org/posts/{post}` | Update post. |
| DELETE | `/org/posts/{post}` | Delete post. |
| POST | `/org/posts/{post}/publish` | Publish post. |
| POST | `/org/posts/{post}/archive` | Archive post. |
| POST | `/org/posts/{post}/restore` | Restore post. |

Post fields include `id`, `title`, `summary`, `type`, `status`, `organizationName`, `authorName`, `location`, `campaignTitle`, `submittedAt`, `createdAt`, `updatedAt`, `publishedAt`, `reviewedBy`, `rejectionReason`, `viewsCount`, `reactionsCount`, and `applicationsCount`.

### Donors / applicants

| Method | Endpoint | Purpose |
|---|---|---|
| GET/POST | `/org/donors` | List/create donor records. |
| GET/PATCH/DELETE | `/org/donors/{donor}` | Show/update/delete donor. |
| GET/POST | `/org/applicants` | List/create applicant records. |
| GET/PATCH/DELETE | `/org/applicants/{applicant}` | Show/update/delete applicant. |

Donor fields include `id`, `name`, `email`, `phone`, `campaignTitle`, `amountOrType`, `donatedAt`, `city`, `source`, `paymentMethod`, `campaignRef`, `assignedTo`, and `internalNotes`.

Applicant fields include `id`, `name`, `email`, `phone`, `campaignTitle`, `amountOrType`, `applicantStatus`, `donatedAt`, `appliedAt`, `city`, `source`, `campaignRef`, `assignedTo`, `internalNotes`, and `requestType`.

### Notifications / reports

| Method | Endpoint | Purpose |
|---|---|---|
| GET/POST | `/org/notifications` | List/create notifications. |
| GET/PATCH/DELETE | `/org/notifications/{notification}` | Show/update/delete notification. |
| PATCH | `/org/notifications/{notification}/read-state` | Mark read/unread. |
| POST | `/org/notifications/{notification}/resend` | Resend notification. |
| GET | `/org/reports` | List reports. |
| GET | `/org/reports/{report}` | Show report. |

### Settings

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/org/settings/profile` | Read organization profile. |
| PATCH | `/org/settings/profile` | Update organization profile. |
| GET | `/org/settings/bank-account` | Read bank account. |
| PATCH | `/org/settings/bank-account` | Update bank account. |

### Staff / roles / permissions

| Method | Endpoint | Purpose |
|---|---|---|
| GET/POST | `/org/staff` | List/create staff members. |
| GET/PATCH/DELETE | `/org/staff/{staff}` | Show/update/delete staff. |
| GET/POST | `/org/roles` | List/create roles. |
| GET/PATCH/DELETE | `/org/roles/{role}` | Show/update/delete role. |
| GET | `/org/permissions/catalog` | Permission catalog for role forms. |

Staff fields include `id`, `name`, `email`, `phone`, `role`, `status`, `invitedAt`, and `acceptedAt`.

Role fields include `id`, `role`, `description`, `permissions`, `isActive`, `isSystem`, `membersCount`, and `updatedAt`.

---

## 8. Frontend Integration Checklist

- [ ] Import `/docs/api.json` into Postman or OpenAPI tooling.
- [ ] Configure `API_BASE_URL`.
- [ ] Add request interceptor for default headers and bearer token.
- [ ] Add response interceptor for `401`, `403`, `422`, `429`, and `500`.
- [ ] Add shared paginator normalizer for Laravel `links` and `meta`.
- [ ] Add shared validation error mapper for `errors`.
- [ ] Use `/me/permissions` to hide inaccessible menus and actions.
- [ ] Re-export docs after backend route/resource/request changes with `composer api-docs`.
