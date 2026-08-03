# Company Authentication API Contract

**Version:** 1.0  
**Status:** Draft implementation contract  
**Branch:** `agent/company-auth-endpoints`  
**Pull request:** `MustafaFares445/jod#9`

## 1. Overview

This contract defines the public authentication endpoints for company and organization accounts.

| Property | Value |
|---|---|
| Base API path | `/api/v1/company/auth` |
| Content type | `application/json` |
| Authentication required | No |
| Token type returned | Laravel Sanctum bearer token pair |
| Date format | ISO 8601 |

A company login is available to an active user whose `organization_id` is not null. Registration creates a new organization and its initial owner account in one database transaction.

## 2. Common Success Response

Successful responses use the following envelope:

```json
{
  "data": {},
  "message": "Operation message",
  "statusCode": 200,
  "item": {}
}
```

`item` mirrors `data` for frontend compatibility. Clients should use one consistently and should not assume the two fields contain different values.

### Authentication payload

Both successful endpoints return this object under `data` and `item`:

| Field | Type | Description |
|---|---|---|
| `token` | string | Plain-text access token used as a bearer token. |
| `refreshToken` | string | Plain-text refresh token used with the refresh endpoint. |
| `tokenType` | string | Always `Bearer`. |
| `expiresIn` | integer | Access-token lifetime in seconds. Default: `3600`. |
| `refreshExpiresIn` | integer | Refresh-token lifetime in seconds. Default: `2592000`. |
| `expiresAt` | string | Access-token expiration timestamp in ISO 8601 format. |
| `refreshExpiresAt` | string | Refresh-token expiration timestamp in ISO 8601 format. |
| `user` | object | Authenticated company user. |
| `permissions` | object | Structured and flattened permission data. |

Token lifetimes can be changed through `AUTH_ACCESS_TOKEN_LIFETIME_MINUTES` and `AUTH_REFRESH_TOKEN_LIFETIME_MINUTES`.

### User object

| Field | Type | Nullable | Description |
|---|---|---:|---|
| `id` | string | No | User UUID. |
| `name` | string | No | User display name. |
| `email` | string | No | User email address. |
| `phone` | string | Yes | User phone number. |
| `userType` | string | No | Registration currently creates `general`. |
| `status` | string | No | Account status, such as `active`. |
| `organizationId` | string | No | Associated organization UUID. |
| `postsCount` | integer | Yes | Loaded post count when available. |
| `reportsCount` | integer | Yes | Loaded report count when available. |
| `createdAt` | string | Yes | ISO 8601 creation timestamp. |
| `updatedAt` | string | Yes | ISO 8601 update timestamp. |
| `lastActiveAt` | string | Yes | ISO 8601 last-activity timestamp. |

### Permissions object

| Field | Type | Description |
|---|---|---|
| `modules` | array | Hierarchical modules, groups, and permission entries for UI rendering. |
| `flat` | object | Map of permission names to booleans. |
| `granted` | string[] | Names of all granted permissions. |

Module entries contain `key`, `label`, `order`, and `groups`. Group entries contain `key`, `label`, `sectionKey`, `sectionLabel`, `description`, `order`, `depth`, and `permissions`. Each permission entry contains `key`, `name`, `label`, and `allowed`.

---

## 3. Company Login

Authenticates an existing active organization-linked user.

### Request

```http
POST /api/v1/company/auth/login
Content-Type: application/json
```

```json
{
  "email": "hassan@techforgood.org",
  "password": "password"
}
```

### Request fields

| Field | Type | Required | Validation |
|---|---|---:|---|
| `email` | string | Yes | Valid email; maximum 255 characters. |
| `password` | string | Yes | Minimum 8 characters. |

### cURL

```bash
curl --request POST \
  --url "${BASE_URL}/api/v1/company/auth/login" \
  --header "Content-Type: application/json" \
  --data '{
    "email": "hassan@techforgood.org",
    "password": "password"
  }'
```

### Success response

**Status:** `200 OK`

```json
{
  "data": {
    "token": "1|access-token-secret",
    "refreshToken": "2|refresh-token-secret",
    "tokenType": "Bearer",
    "expiresIn": 3600,
    "refreshExpiresIn": 2592000,
    "expiresAt": "2026-08-03T16:00:00+00:00",
    "refreshExpiresAt": "2026-09-02T15:00:00+00:00",
    "user": {
      "id": "7ba9f041-6401-4f38-9164-2ab54a663f73",
      "name": "حسن أحمد",
      "email": "hassan@techforgood.org",
      "phone": "+962791234574",
      "userType": "general",
      "status": "active",
      "organizationId": "35a40219-47fd-4703-937b-e0f7bf17ea98",
      "postsCount": null,
      "reportsCount": null,
      "createdAt": "2026-06-03T15:00:00+00:00",
      "updatedAt": "2026-08-03T15:00:00+00:00",
      "lastActiveAt": "2026-08-03T15:00:00+00:00"
    },
    "permissions": {
      "modules": [],
      "flat": {
        "dashboard.view": true
      },
      "granted": [
        "dashboard.view"
      ]
    }
  },
  "message": "Company logged in successfully",
  "statusCode": 200,
  "item": {
    "token": "1|access-token-secret",
    "refreshToken": "2|refresh-token-secret",
    "tokenType": "Bearer",
    "expiresIn": 3600,
    "refreshExpiresIn": 2592000,
    "expiresAt": "2026-08-03T16:00:00+00:00",
    "refreshExpiresAt": "2026-09-02T15:00:00+00:00",
    "user": {
      "id": "7ba9f041-6401-4f38-9164-2ab54a663f73",
      "name": "حسن أحمد",
      "email": "hassan@techforgood.org",
      "phone": "+962791234574",
      "userType": "general",
      "status": "active",
      "organizationId": "35a40219-47fd-4703-937b-e0f7bf17ea98",
      "postsCount": null,
      "reportsCount": null,
      "createdAt": "2026-06-03T15:00:00+00:00",
      "updatedAt": "2026-08-03T15:00:00+00:00",
      "lastActiveAt": "2026-08-03T15:00:00+00:00"
    },
    "permissions": {
      "modules": [],
      "flat": {
        "dashboard.view": true
      },
      "granted": [
        "dashboard.view"
      ]
    }
  }
}
```

The permissions shown above are abbreviated. The real response includes the complete catalog available to the account.

### Error responses

#### Invalid credentials

**Status:** `401 Unauthorized`

```json
{
  "message": "The provided company credentials are incorrect."
}
```

This response is also used when the email belongs to a user without an organization association.

#### Inactive account

**Status:** `403 Forbidden`

```json
{
  "message": "This company account is not active."
}
```

#### Validation failure

**Status:** `422 Unprocessable Entity`

```json
{
  "message": "The email field must be a valid email address.",
  "errors": {
    "email": [
      "The email field must be a valid email address."
    ]
  }
}
```

Validation text may vary with the server locale. Clients should use the HTTP status and field keys rather than matching exact messages.

---

## 4. Company Registration

Creates a pending organization and its active owner account, then immediately returns an authentication token pair.

### Request

```http
POST /api/v1/company/auth/register
Content-Type: application/json
```

```json
{
  "companyName": "شركة الأثر المجتمعي",
  "companyEmail": "contact@socialimpact.example",
  "companyPhone": "+962790000001",
  "organizationType": "social_enterprise",
  "registrationNumber": "SE-2026-100",
  "location": "عمّان، الأردن",
  "ownerName": "ليان خالد",
  "ownerEmail": "layan@socialimpact.example",
  "ownerPhone": "+962790000002",
  "password": "StrongPassword123!",
  "password_confirmation": "StrongPassword123!",
  "description": "شركة اجتماعية تدعم المبادرات المجتمعية.",
  "website": "https://socialimpact.example",
  "establishmentDate": "2024-05-20"
}
```

### Request fields

| Field | Type | Required | Validation |
|---|---|---:|---|
| `companyName` | string | Yes | Maximum 255 characters. |
| `companyEmail` | string | Yes | Valid email; maximum 255 characters; unique in organizations. |
| `companyPhone` | string | Yes | Maximum 30 characters. |
| `organizationType` | string | Yes | Maximum 100 characters. |
| `registrationNumber` | string | Yes | Maximum 100 characters; unique in organizations. |
| `location` | string | Yes | Maximum 255 characters. |
| `ownerName` | string | Yes | Maximum 255 characters. |
| `ownerEmail` | string | Yes | Valid email; maximum 255 characters; unique in users. |
| `ownerPhone` | string | Yes | Maximum 30 characters. |
| `password` | string | Yes | Minimum 8 characters; must match `password_confirmation`. |
| `password_confirmation` | string | Yes | Must exactly match `password`. The key is snake_case. |
| `description` | string | No | Nullable; maximum 2000 characters. |
| `website` | string | No | Nullable; valid URL; maximum 255 characters. |
| `establishmentDate` | string | No | Nullable date; cannot be after the current server date. |

No fixed enum is currently enforced for `organizationType`; the API accepts any string up to 100 characters.

### cURL

```bash
curl --request POST \
  --url "${BASE_URL}/api/v1/company/auth/register" \
  --header "Content-Type: application/json" \
  --data '{
    "companyName": "شركة الأثر المجتمعي",
    "companyEmail": "contact@socialimpact.example",
    "companyPhone": "+962790000001",
    "organizationType": "social_enterprise",
    "registrationNumber": "SE-2026-100",
    "location": "عمّان، الأردن",
    "ownerName": "ليان خالد",
    "ownerEmail": "layan@socialimpact.example",
    "ownerPhone": "+962790000002",
    "password": "StrongPassword123!",
    "password_confirmation": "StrongPassword123!",
    "description": "شركة اجتماعية تدعم المبادرات المجتمعية.",
    "website": "https://socialimpact.example",
    "establishmentDate": "2024-05-20"
  }'
```

### Success response

**Status:** `201 Created`

```json
{
  "data": {
    "token": "3|access-token-secret",
    "refreshToken": "4|refresh-token-secret",
    "tokenType": "Bearer",
    "expiresIn": 3600,
    "refreshExpiresIn": 2592000,
    "expiresAt": "2026-08-03T16:00:00+00:00",
    "refreshExpiresAt": "2026-09-02T15:00:00+00:00",
    "user": {
      "id": "ac9ca569-a2cb-475e-adf7-f853cc4259be",
      "name": "ليان خالد",
      "email": "layan@socialimpact.example",
      "phone": "+962790000002",
      "userType": "general",
      "status": "active",
      "organizationId": "e80254e4-553d-4987-b734-d27795be5d86",
      "postsCount": null,
      "reportsCount": null,
      "createdAt": "2026-08-03T15:00:00+00:00",
      "updatedAt": "2026-08-03T15:00:00+00:00",
      "lastActiveAt": "2026-08-03T15:00:00+00:00"
    },
    "permissions": {
      "modules": [],
      "flat": {
        "dashboard.view": true
      },
      "granted": [
        "dashboard.view"
      ]
    }
  },
  "message": "Company registered successfully",
  "statusCode": 201,
  "item": {
    "token": "3|access-token-secret",
    "refreshToken": "4|refresh-token-secret",
    "tokenType": "Bearer",
    "expiresIn": 3600,
    "refreshExpiresIn": 2592000,
    "expiresAt": "2026-08-03T16:00:00+00:00",
    "refreshExpiresAt": "2026-09-02T15:00:00+00:00",
    "user": {
      "id": "ac9ca569-a2cb-475e-adf7-f853cc4259be",
      "name": "ليان خالد",
      "email": "layan@socialimpact.example",
      "phone": "+962790000002",
      "userType": "general",
      "status": "active",
      "organizationId": "e80254e4-553d-4987-b734-d27795be5d86",
      "postsCount": null,
      "reportsCount": null,
      "createdAt": "2026-08-03T15:00:00+00:00",
      "updatedAt": "2026-08-03T15:00:00+00:00",
      "lastActiveAt": "2026-08-03T15:00:00+00:00"
    },
    "permissions": {
      "modules": [],
      "flat": {
        "dashboard.view": true
      },
      "granted": [
        "dashboard.view"
      ]
    }
  }
}
```

The permissions shown above are abbreviated. The registered owner receives the system owner role and the complete organization permission set.

### Created records and initial state

Registration is transactional. Either all records are created or none are created.

| Record | Initial state |
|---|---|
| Organization | `status: pending`, `verification_status: pending` |
| Owner user | `user_type: general`, `status: active` |
| Owner role | Active system role named `المالك`; full organization permissions |
| Staff membership | Active and accepted; linked to the owner role |

A pending organization is still issued tokens by the current implementation. Organization approval requirements should therefore be enforced by protected business endpoints or added to authentication separately if needed.

### Error response

#### Validation or uniqueness failure

**Status:** `422 Unprocessable Entity`

```json
{
  "message": "The owner email has already been taken.",
  "errors": {
    "ownerEmail": [
      "The owner email has already been taken."
    ]
  }
}
```

Possible error keys include:

- `companyName`
- `companyEmail`
- `companyPhone`
- `organizationType`
- `registrationNumber`
- `location`
- `ownerName`
- `ownerEmail`
- `ownerPhone`
- `password`
- `description`
- `website`
- `establishmentDate`

Laravel reports password-confirmation mismatch under the `password` key.

---

## 5. Using the Access Token

Send the returned access token to protected endpoints:

```http
Authorization: Bearer <token>
```

Example:

```bash
curl --request GET \
  --url "${BASE_URL}/api/v1/me" \
  --header "Authorization: Bearer ${ACCESS_TOKEN}" \
  --header "Accept: application/json"
```

Do not use `refreshToken` as the bearer token for protected resources. It has a separate Sanctum ability and is intended only for token rotation.

## 6. Seeded Company Accounts

All seeded accounts below use the password `password`.

| Email | Organization | Purpose |
|---|---|---|
| `hassan@techforgood.org` | التقنية من أجل الخير | New company seed |
| `noor@ammangroup.org` | مجموعة مجتمع عمّان | New company seed |
| `sarah@helpfoundation.org` | مؤسسة العون | Existing organization owner |
| `fatima@educationinitiative.org` | مبادرة التعليم | Existing organization owner |

Seed credentials are for local or test environments only and must not be deployed as production credentials.

## 7. Client Handling Requirements

1. Store the access and refresh tokens securely and separately.
2. Use `tokenType` when constructing the authorization header.
3. Schedule access-token renewal using `expiresAt` or `expiresIn`.
4. Treat `401`, `403`, and `422` as distinct cases.
5. Render field errors from the `errors` object rather than parsing `message`.
6. Do not depend on validation-message wording because it may change with localization.
7. Treat `item` as a compatibility alias of `data`.
