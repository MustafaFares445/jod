# Mobile User Flow API Contract

This document is the frontend/mobile contract for the account, profile, awareness content, personal publishing, search, saved posts, and post-reporting flows.

## Base paths

Mobile endpoints use:

```text
/api/mobile
```

The shared media manager uses:

```text
/api/v1/media
```

Authenticated mobile endpoints require:

```http
Authorization: Bearer <access-token>
Accept: application/json
```

---

## 1. Register

```http
POST /api/mobile/auth/register
```

All fields are required:

```json
{
  "name": "User Name",
  "email": "user@example.com",
  "phone": "+963900000000",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Validation:

- `name`: required string, max 255
- `email`: required valid unique email
- `phone`: required unique string, max 20
- `password`: required, minimum 8, confirmed
- `password_confirmation`: required

A successful response returns the user plus access/refresh token pair.

---

## 2. Login

Primary app flow:

```http
POST /api/mobile/auth/login
```

```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

Email + password is the intended UI flow. Phone login remains accepted for backwards compatibility with older clients.

---

## 3. Personal profile

### Get profile

```http
GET /api/mobile/me
```

Profile includes:

- `id`
- `name`
- `email`
- `phone`
- `city`
- `bio`
- account status/type
- organization data when applicable
- profile counters

### Update profile

```http
PATCH /api/mobile/me/profile
```

```json
{
  "name": "Updated Name",
  "email": "updated@example.com",
  "phone": "+963944444444",
  "city": "Damascus",
  "bio": "Short profile description"
}
```

Rules:

- `name`: required, max 255
- `email`: required, valid and unique except current user
- `phone`: nullable, unique except current user, max 20
- `city`: nullable, 2-100 characters
- `bio`: nullable, max 180 characters

Password fields are not part of this request.

---

## 4. Change password

Separate from profile update:

```http
PATCH /api/mobile/me/change-password
```

```json
{
  "currentPassword": "old-password",
  "password": "new-password123",
  "password_confirmation": "new-password123"
}
```

Rules:

- current password must be correct
- new password minimum 8
- confirmation must match
- new password must differ from current password

---

## 5. Awareness articles / educational content

### List

```http
GET /api/mobile/discovery/articles
```

### Detail

```http
GET /api/mobile/discovery/articles/{articleId}
```

These routes are public and throttled.

---

## 6. Public post list and detail

### List / discovery

```http
GET /api/mobile/discovery/posts
```

Useful query parameters:

```text
search
location
categoryId
category
sort=newest|oldest
page
perPage
```

Public discovery treats both internal `published` and admin-reviewed `approved` posts as public.

### Detail

```http
GET /api/mobile/discovery/posts/{postId}
```

---

## 7. My posts

```http
GET /api/mobile/me/posts
```

Filter by status:

```text
filter[status]=draft
filter[status]=pending
filter[status]=active
filter[status]=rejected
filter[status]=archived
```

Mobile status mapping:

| Mobile status | Internal status |
|---|---|
| `draft` | `draft` |
| `pending` | `pending` |
| `active` | `published` or `approved` |
| `rejected` | `rejected` |
| `archived` | `archived` |

### My post detail

```http
GET /api/mobile/me/posts/{postId}
```

This endpoint can return the authenticated user's draft, pending, rejected, active, or archived post. Another user cannot access it.

Response includes media URLs plus media IDs under `imageMedia`, allowing replace/delete operations.

---

## 8. Create a personal post

```http
POST /api/mobile/posts
Content-Type: application/json
```

Normal post data is media-free.

Example:

```json
{
  "type": "help_request",
  "title": "Need winter supplies",
  "details": "A family needs winter supplies and warm clothes.",
  "city": "Damascus",
  "categoryId": "CATEGORY_UUID",
  "saveAsDraft": false
}
```

Supported personal post types:

- `volunteer_opportunity`
- `donation_campaign`
- `help_request`

If `saveAsDraft=false`, title/details/city must be complete and the post is created as `pending`.

If `saveAsDraft=true`, the post is created as `draft` and can then be edited and receive media.

Do **not** send `images`, `image`, or files in this request.

---

## 9. Post images — General Media Manager

New clients must use the shared media manager for all personal post image uploads.

### Upload one file

```http
POST /api/v1/media/post/{postId}/images
Content-Type: multipart/form-data
```

```text
file: <binary image>
```

One file per request. Repeat the endpoint for multiple selected images. Upload sequentially when UI order matters.

### Replace one image

```http
POST /api/v1/media/post/{postId}/images/{mediaId}/replace
Content-Type: multipart/form-data
```

```text
file: <replacement image>
```

### Delete one image

```http
DELETE /api/v1/media/post/{postId}/images/{mediaId}
```

Media can be changed while the personal post is editable (`draft` or `rejected`). Ownership is enforced.

### Recommended create flow when images are selected

1. Create the post as a draft:

```json
{
  "type": "help_request",
  "title": "Need winter supplies",
  "details": "A family needs winter supplies and warm clothes.",
  "city": "Damascus",
  "saveAsDraft": true
}
```

2. Read `data.id` from the create response.
3. Upload zero, one, or multiple images individually through `/api/v1/media/post/{postId}/images`.
4. Submit the completed post:

```http
POST /api/mobile/posts/{postId}/submit
```

After submission, the post becomes `pending` and media changes are no longer allowed until it becomes editable again, such as after rejection.

Legacy mobile-specific image routes remain temporarily for old-client compatibility, but new app code should use only the general media manager.

---

## 10. Edit personal post

```http
PATCH /api/mobile/posts/{postId}
```

Editable while status is `draft` or `rejected`.

Supported fields:

```json
{
  "type": "help_request",
  "title": "Updated title",
  "details": "Updated post description",
  "city": "Damascus",
  "categoryId": "CATEGORY_UUID"
}
```

Media is not accepted in this body; use the media manager.

---

## 11. Submit / archive / repost / delete personal post

Submit draft or rejected post:

```http
POST /api/mobile/posts/{postId}/submit
```

Archive active post:

```http
POST /api/mobile/posts/{postId}/archive
```

Repost archived post:

```http
POST /api/mobile/posts/{postId}/repost
```

Delete owned post:

```http
DELETE /api/mobile/posts/{postId}
```

Deleting a post also purges its generic media records and stored files.

---

## 12. Global search

```http
GET /api/mobile/search
```

Returns all three result groups in one response:

```json
{
  "data": {
    "accounts": [],
    "posts": [],
    "campaigns": []
  },
  "meta": {
    "counts": {
      "accounts": 0,
      "posts": 0,
      "campaigns": 0
    },
    "appliedFilters": {}
  }
}
```

Query parameters:

| Param | Values |
|---|---|
| `search` | free text |
| `type` | `all`, `accounts`, `posts`, `campaigns` |
| `location` | city/location text |
| `category` | category value/name |
| `sort` | `newest`, `oldest` |
| `perType` | 1-20, default 10 |

Examples:

```http
GET /api/mobile/search?search=Winter
GET /api/mobile/search?type=accounts&search=Samar
GET /api/mobile/search?type=posts&location=Damascus&category=health&sort=oldest
GET /api/mobile/search?type=campaigns&location=Damascus&category=health&sort=newest
```

Account results include both organizations and individual user publishers, with `accountType` identifying which type was returned.

---

## 13. Saved posts

### List

```http
GET /api/mobile/me/saved-posts
```

### Save

```http
POST /api/mobile/posts/{postId}/save
```

### Remove from saved

```http
DELETE /api/mobile/posts/{postId}/save
```

Both `published` and admin-approved posts are saveable and appear in the saved list.

---

## 14. Report a post

### Get report reasons

```http
GET /api/mobile/lookups/report-reasons
```

The UI reason codes are:

| Code | Arabic label | Custom text |
|---|---|---|
| `misleading` | محتوى مضلل | optional |
| `abusive` | محتوى مسيء أو غير لائق | optional |
| `fraud` | احتيال أو طلب تبرع مشبوه | optional |
| `impersonation` | انتحال جهة أو شخصية | optional |
| `other` | سبب آخر | required |

### Submit report

```http
POST /api/mobile/posts/{postId}/reports
```

Standard reason:

```json
{
  "reason": "misleading"
}
```

Other reason:

```json
{
  "reason": "other",
  "details": "سبب البلاغ بالتفصيل"
}
```

Rules:

- `reason` is required and must be one of the five codes above
- `details` is required when `reason=other`
- custom details minimum 3 and maximum 180 characters, matching the UI counter

The created moderation report has status `new`, points to the reported post, and stores both the reason code and Arabic reason label for admin review.

---

## 15. Categories and cities lookups

Categories:

```http
GET /api/mobile/discovery/categories
```

Cities:

```http
GET /api/mobile/lookups/cities
```

These can be used to build search/profile/post filters without hard-coding display values in the client.

---

## Frontend integration checklist

- Register sends all 5 required fields.
- Login UI sends email + password.
- Profile form handles name/email/phone/city/bio.
- Password has a separate form/endpoint.
- Awareness page uses discovery articles list/detail.
- My Posts uses the five mobile status filters.
- Personal post detail uses `/me/posts/{id}` for non-public states.
- Never put image files inside post create/update body.
- With images: create draft -> media manager uploads -> submit.
- Saved state uses `/save` POST/DELETE and `/me/saved-posts`.
- Global search uses `/mobile/search` for accounts + posts + campaigns.
- Report sheet should load/display the five reason codes and require text only for `other`.
