# Admin User Posts & Donations API Contract

## Scope

This contract adds admin-only read endpoints for viewing the posts authored by a specific user and the donations created by that user.

Base path: `/api/v1/admin`

Authentication: required via the existing `auth:sanctum` and `access-token` middleware. Authorization uses the existing `UserPolicy::view` permission for the requested user.

---

## 1. Get User Posts

### Request

`GET /api/v1/admin/users/{user}/posts`

### Path parameters

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `user` | string | yes | User ID |

### Query parameters

| Parameter | Type | Required | Default | Rules |
| --- | --- | --- | --- | --- |
| `page` | integer | no | `1` | Pagination page |
| `perPage` | integer | no | `20` | Minimum `1`, maximum `100` |

### Ordering

Posts are ordered by `created_at` descending.

### Success response

HTTP `200`

```json
{
  "data": [
    {
      "id": "post-id",
      "title": "Post title",
      "summary": "Post summary",
      "content": "Post content",
      "description": "Post content",
      "body": "Post content",
      "type": "post",
      "status": "published",
      "organizationName": "Organization name",
      "authorName": "User name",
      "author": {
        "id": "user-id",
        "name": "User name",
        "email": "user@example.com"
      },
      "location": "Amman",
      "campaignTitle": "Campaign title",
      "images": [],
      "media": [],
      "submittedAt": "2026-08-24T12:00:00+00:00",
      "createdAt": "2026-08-24T12:00:00+00:00",
      "updatedAt": "2026-08-24T12:00:00+00:00",
      "publishedAt": "2026-08-24T12:00:00+00:00",
      "viewsCount": 0,
      "reactionsCount": 0,
      "applicationsCount": 0
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "...",
    "per_page": 20,
    "to": 1,
    "total": 1
  }
}
```

The response uses the existing `PostResource`, so the admin frontend receives the same post representation already used by the backend.

---

## 2. Get User Donations

### Request

`GET /api/v1/admin/users/{user}/donations`

### Path parameters

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `user` | string | yes | User ID |

### Query parameters

| Parameter | Type | Required | Default | Rules |
| --- | --- | --- | --- | --- |
| `page` | integer | no | `1` | Pagination page |
| `perPage` | integer | no | `20` | Minimum `1`, maximum `100` |

### Ordering

Donations are ordered by `donated_at` descending and then `created_at` descending.

### Success response

HTTP `200`

```json
{
  "data": [
    {
      "id": "1",
      "campaignId": "campaign-id",
      "campaignTitle": "Campaign title",
      "organizationId": "organization-id",
      "organizationName": "Organization name",
      "name": "Donor name",
      "email": "donor@example.com",
      "phone": "+962700000000",
      "amountOrType": "50.00",
      "amount": 50,
      "paymentMethod": "card",
      "city": "Amman",
      "source": "mobile",
      "campaignRef": "campaign-reference",
      "internalNotes": null,
      "donatedAt": "2026-08-24T12:00:00+00:00",
      "createdAt": "2026-08-24T12:00:00+00:00",
      "updatedAt": "2026-08-24T12:00:00+00:00"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "...",
    "per_page": 20,
    "to": 1,
    "total": 1
  }
}
```

`amount` is numeric when `amount_or_type` contains a numeric value; otherwise it is `null`, while `amountOrType` preserves the original stored value.

---

## Error responses

### Unauthenticated

HTTP `401`

Returned when the access token is missing or invalid.

### Forbidden

HTTP `403`

Returned when the authenticated account is not authorized to view the requested user.

### User not found

HTTP `404`

Returned when `{user}` does not match an existing user.

---

## Admin frontend integration

For the admin user details page:

- Load posts from `GET /api/v1/admin/users/{user}/posts`.
- Load donations from `GET /api/v1/admin/users/{user}/donations`.
- Keep pagination state independent for the Posts and Donations tabs/sections.
- Send `perPage` using camelCase exactly as documented.
- Read records from the top-level `data` array and pagination from `meta` / `links`.
- Do not expect an `item` wrapper.
