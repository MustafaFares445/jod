# Frontend Integration Guide — Organization, Campaign, Post & Donor API Updates

This document describes the frontend-facing API changes merged in PR #41.

> Backend repository: `MustafaFares445/jod`
> Base API examples below assume the existing `/api` prefix.

## 1. Summary of frontend changes

The frontend should update the following flows:

1. Organization registration fields changed and now include an organization image/logo.
2. Organization settings return and update the same organization fields used during registration.
3. Organization password update has a dedicated endpoint.
4. Campaigns support up to 10 uploaded images.
5. New organization campaigns are `active` by default and do not require admin review.
6. Organization posts support up to 10 uploaded images.
7. New organization posts are `published` by default and do not require admin review.
8. A non-paginated categories brief endpoint is available for selectors.
9. Campaign filtering by Syrian governorate uses exact governorate values.
10. Donor create/update requires only name, email, and Syrian mobile number.
11. A non-paginated campaign brief endpoint is available for selectors.
12. A role cannot be deleted while it is assigned to any staff member, including inactive staff.

---

## 2. Authentication

All `/api/v1/org/*` endpoints require the existing authenticated organization session/token and the organization must be active.

The organization registration endpoint is public.

---

## 3. Organization registration

### Endpoint

```http
POST /api/v1/company/auth/register
Content-Type: multipart/form-data
```

### Required fields

| Field | Type | Required | Notes |
|---|---|---:|---|
| `companyName` | string | yes | Organization name, max 255 |
| `ownerName` | string | yes | Owner name, max 255 |
| `organizationNumber` | string | yes | Unique organization number, max 100 |
| `registrationNumber` | string | yes | Unique registration number, max 100 |
| `bankAccountNumber` | string | yes | Bank account number, max 100 |
| `companyEmail` | email | yes | Unique in organizations and users |
| `companyPhone` | string | yes | Official organization phone, max 30 |
| `location` | string | yes | Organization city/location |
| `website` | URL | no | Optional |
| `image` | file | yes* | Organization image/logo |
| `logo` | file | no | Alias for `image`; `image` is required unless `logo` is sent |
| `password` | string | yes | Minimum 8 characters |
| `password_confirmation` | string | yes | Must match `password` |

### Organization image rules

Accepted formats:

```text
jpg, jpeg, png, webp
```

Maximum size:

```text
5 MB
```

### Frontend FormData example

```ts
const form = new FormData();
form.append('companyName', values.companyName);
form.append('ownerName', values.ownerName);
form.append('organizationNumber', values.organizationNumber);
form.append('registrationNumber', values.registrationNumber);
form.append('bankAccountNumber', values.bankAccountNumber);
form.append('companyEmail', values.companyEmail);
form.append('companyPhone', values.companyPhone);
form.append('location', values.location);
if (values.website) form.append('website', values.website);
form.append('image', values.image);
form.append('password', values.password);
form.append('password_confirmation', values.passwordConfirmation);
```

### Response

Successful registration returns HTTP `201` with the existing authentication token pair, user data, and permissions.

```json
{
  "data": {
    "accessToken": "...",
    "refreshToken": "...",
    "user": {},
    "permissions": []
  },
  "message": "Company registered successfully"
}
```

Use the real token field names already handled by the frontend auth client; the registration response uses the same backend token service as the rest of authentication.

---

## 4. Organization settings/profile

### Get organization profile

```http
GET /api/v1/org/settings/profile
```

Compatibility endpoint also exists:

```http
GET /api/v1/org/profile
```

### Response shape

```json
{
  "data": {
    "id": "organization-id",
    "companyName": "Organization Name",
    "ownerName": "Owner Name",
    "organizationNumber": "ORG-001",
    "registrationNumber": "REG-001",
    "bankAccountNumber": "BANK-ACCOUNT",
    "companyEmail": "official@example.com",
    "companyPhone": "+963...",
    "location": "Damascus",
    "website": "https://example.com",
    "image": "https://.../storage/organizations/logos/..."
  }
}
```

`website` and `image` may be `null`.

### Update organization profile

```http
PATCH /api/v1/org/settings/profile
```

Compatibility endpoint:

```http
PUT /api/v1/org/profile
```

Accepted fields are the same frontend-facing organization fields:

```text
companyName
ownerName
organizationNumber
registrationNumber
bankAccountNumber
companyEmail
companyPhone
location
website
image
```

All fields are optional during update. When `image` is sent, the old organization image is replaced.

If a file is included, use `multipart/form-data`.

### TypeScript model

```ts
export type OrganizationProfile = {
  id: string;
  companyName: string;
  ownerName: string;
  organizationNumber: string;
  registrationNumber: string;
  bankAccountNumber: string;
  companyEmail: string;
  companyPhone: string;
  location: string;
  website: string | null;
  image: string | null;
};
```

---

## 5. Dedicated organization password update

### Endpoint

```http
PATCH /api/v1/org/settings/password
Content-Type: application/json
```

### Request

```json
{
  "currentPassword": "current-password",
  "newPassword": "new-password",
  "newPassword_confirmation": "new-password"
}
```

Rules:

- `currentPassword` is required and must match the current password.
- `newPassword` is required.
- Minimum length is 8.
- It must be different from `currentPassword`.
- `newPassword_confirmation` is required and must match `newPassword`.

### Success response

```json
{
  "data": {
    "id": "user-id",
    "name": "...",
    "email": "...",
    "phone": "...",
    "userType": "...",
    "organizationId": "...",
    "organizationName": "...",
    "status": "...",
    "createdAt": "...",
    "lastActiveAt": "..."
  },
  "message": "Password updated successfully."
}
```

Do not send password fields through the organization profile endpoint.

---

## 6. Campaign images and publishing behavior

### Create campaign

```http
POST /api/v1/org/campaigns
Content-Type: multipart/form-data
```

### Campaign fields

| Field | Required on create | Notes |
|---|---:|---|
| `title` | yes | string, max 255 |
| `summary` | yes | string |
| `category` | yes | one of the allowed values below |
| `status` | no | `draft` or `active`; defaults to `active` |
| `location` | yes | exact Syrian governorate value |
| `goalAmount` | yes | number >= 0 |
| `beneficiariesCount` | yes | integer >= 0 |
| `startDate` | yes | date |
| `endDate` | yes | date >= `startDate` |
| `images[]` | no | up to 10 image files |

### Allowed campaign categories

```ts
export const campaignCategories = [
  'health',
  'education',
  'food',
  'shelter',
  'employment',
  'emergency',
  'donation',
  'volunteer',
  'community',
] as const;
```

### Campaign image rules

- Field name: `images[]`
- Maximum: 10 images
- Per-image maximum size: 5 MB
- Accepted: `jpg`, `jpeg`, `png`, `webp`

```ts
for (const image of values.images) {
  form.append('images[]', image);
}
```

### Important publishing change

If the frontend omits `status` when creating a campaign, the backend creates it as:

```text
active
```

There is no admin-review step for organization campaign creation.

If the organization wants to save without publishing, explicitly send:

```text
status=draft
```

### Campaign response image field

Campaign responses now contain:

```json
{
  "images": [
    "https://.../storage/campaigns/.../image1.jpg",
    "https://.../storage/campaigns/.../image2.jpg"
  ]
}
```

### Updating campaign images

When new image files are sent to the campaign update endpoint, the backend replaces the existing campaign image set with the newly uploaded files.

```http
PATCH /api/v1/org/campaigns/{campaignId}
```

Important: sending new `images[]` is replacement behavior, not append behavior.

---

## 7. Syrian governorate campaign filter

Campaign locations must use one of these exact values:

```ts
export const syrianGovernorates = [
  'Damascus',
  'Rif Dimashq',
  'Aleppo',
  'Homs',
  'Hama',
  'Latakia',
  'Tartus',
  'Idlib',
  'Deir ez-Zor',
  'Raqqa',
  'Hasakah',
  'Daraa',
  'As-Suwayda',
  'Quneitra',
] as const;
```

### Organization dashboard campaign filter

```http
GET /api/v1/org/campaigns?filter[location]=Damascus
```

The backend uses exact equality for the governorate filter. Do not send translated labels as the API value unless the displayed label maps back to the exact English value above.

Example select option:

```ts
{
  label: 'دمشق',
  value: 'Damascus'
}
```

---

## 8. Organization post images and direct publishing

### Create organization post

```http
POST /api/v1/org/posts
Content-Type: multipart/form-data
```

### Fields

| Field | Required on create | Notes |
|---|---:|---|
| `title` | yes | string, max 255 |
| `summary` | yes | string |
| `type` | yes | allowed values below |
| `status` | no | `draft` or `published`; defaults to `published` |
| `location` | yes | string, max 255 |
| `campaignTitle` | conditional | required for campaign-related post types |
| `images[]` | no | up to 10 images |

### Allowed post types

```ts
export const organizationPostTypes = [
  'general',
  'job_opportunity',
  'campaign_teaser',
  'campaign_update',
  'campaign_summary',
] as const;
```

`campaignTitle` is required when `type` is one of:

```text
campaign_teaser
campaign_update
campaign_summary
```

The campaign title must belong to the authenticated organization.

### Post image rules

- Field name: `images[]`
- Maximum: 10 images
- Per-image maximum size: 5 MB
- Accepted: `jpg`, `jpeg`, `png`, `webp`

### Important publishing change

If `status` is omitted during create, the post is created as:

```text
published
```

Organization posts do not require admin approval before appearing as published content.

To save as a draft, explicitly send:

```text
status=draft
```

### Post response

The existing post resource now returns uploaded image URLs in:

```json
{
  "images": [
    "https://.../storage/posts/.../image1.jpg"
  ]
}
```

### Updating post images

```http
PATCH /api/v1/org/posts/{postId}
```

When new `images[]` files are provided, all existing post images are deleted and replaced with the new uploaded set.

This is replacement behavior, not append behavior.

---

## 9. Brief categories endpoint

Use this endpoint for dropdowns/selectors that only need category IDs and names.

```http
GET /api/v1/org/categories/brief
```

Properties:

- No pagination.
- Returns all categories.
- Sorted by name.
- Only returns `id` and `name`.

### Response

```json
{
  "data": [
    {
      "id": "category-id",
      "name": "Category Name"
    }
  ]
}
```

### TypeScript type

```ts
export type BriefOption = {
  id: string;
  name: string;
};
```

---

## 10. Brief campaigns endpoint

Use this endpoint for organization campaign selectors, including the post form.

```http
GET /api/v1/org/campaigns/brief
```

Properties:

- No pagination.
- Organization-scoped.
- Sorted by campaign title.
- Returns `id` and `name` only.
- `name` is the campaign title.

### Response

```json
{
  "data": [
    {
      "id": "campaign-id",
      "name": "Campaign Name"
    }
  ]
}
```

The current post create/update API still accepts `campaignTitle`, not `campaignId`. Therefore, when using this brief endpoint in a post form, submit the selected item's `name` as `campaignTitle`.

Example:

```ts
form.append('campaignTitle', selectedCampaign.name);
```

---

## 11. Donor create/update

### Endpoints

```http
POST /api/v1/org/donors
PATCH /api/v1/org/donors/{donorId}
```

### Required payload

Only these three fields are required/accepted by the updated donor request contract:

```json
{
  "name": "Donor Name",
  "email": "donor@example.com",
  "phone": "0912345678"
}
```

### Phone validation

The phone must:

- contain exactly 10 digits,
- start with `09`.

Regex used by the backend:

```regex
^09\d{8}$
```

Frontend recommendation:

```ts
const syrianMobileRegex = /^09\d{8}$/;
```

Remove any previously required donor fields from frontend validation and request construction.

---

## 12. Role deletion protection

Existing role delete endpoint:

```http
DELETE /api/v1/org/roles/{roleId}
```

The same controller is also exposed under the staff roles resource.

### New behavior

A role cannot be deleted if any staff member is assigned to it.

This includes:

- active staff,
- inactive staff,
- any other staff record still assigned to the role.

The backend responds with HTTP `409 Conflict` and the message:

```text
Roles assigned to staff cannot be deleted. Remove every staff assignment first.
```

System roles also cannot be deleted and return HTTP `409`.

Frontend behavior should disable/hide destructive deletion where `staffCount > 0` when that information is available, but still handle the backend `409` as the source of truth.

---

## 13. Suggested frontend API constants

```ts
export const orgApi = {
  register: '/api/v1/company/auth/register',
  profile: '/api/v1/org/settings/profile',
  password: '/api/v1/org/settings/password',
  campaigns: '/api/v1/org/campaigns',
  campaignsBrief: '/api/v1/org/campaigns/brief',
  posts: '/api/v1/org/posts',
  categoriesBrief: '/api/v1/org/categories/brief',
  donors: '/api/v1/org/donors',
  roles: '/api/v1/org/roles',
} as const;
```

---

## 14. FormData helper example

A reusable helper can simplify repeated image array handling:

```ts
export function appendImages(form: FormData, images: File[]) {
  images.forEach((image) => form.append('images[]', image));
}
```

Campaign example:

```ts
const form = new FormData();
form.append('title', values.title);
form.append('summary', values.summary);
form.append('category', values.category);
form.append('location', values.location);
form.append('goalAmount', String(values.goalAmount));
form.append('beneficiariesCount', String(values.beneficiariesCount));
form.append('startDate', values.startDate);
form.append('endDate', values.endDate);
appendImages(form, values.images);
```

Post example:

```ts
const form = new FormData();
form.append('title', values.title);
form.append('summary', values.summary);
form.append('type', values.type);
form.append('location', values.location);

if (values.campaign) {
  form.append('campaignTitle', values.campaign.name);
}

appendImages(form, values.images);
```

Do not manually force a `multipart/form-data` boundary in browsers. Let `fetch`/Axios generate the correct `Content-Type` boundary for `FormData`.

---

## 15. Frontend migration checklist

- [ ] Update organization registration form with the new required fields.
- [ ] Add required organization image/logo upload to registration.
- [ ] Remove old registration fields that are no longer part of the backend request contract.
- [ ] Update organization settings model to use the registration-equivalent profile fields.
- [ ] Add organization image preview/update.
- [ ] Move password editing to `/api/v1/org/settings/password`.
- [ ] Add campaign multiple-image upload UI, max 10.
- [ ] Treat new campaigns as immediately active unless the user explicitly chooses draft.
- [ ] Use exact Syrian governorate API values for campaign location/filtering.
- [ ] Add post multiple-image upload UI, max 10.
- [ ] Treat new organization posts as immediately published unless explicitly draft.
- [ ] Use `/categories/brief` for category dropdowns where full category pagination is unnecessary.
- [ ] Use `/campaigns/brief` for campaign dropdowns.
- [ ] Submit the selected campaign `name` as `campaignTitle` in post requests.
- [ ] Reduce donor form validation to name/email/phone.
- [ ] Validate donor phone with `^09\d{8}$`.
- [ ] Handle role deletion HTTP `409` when any staff assignment exists.
- [ ] When replacing campaign/post images, warn the user that uploading a new set replaces the existing set.

---

## 16. Important behavior notes

### Campaign/post review

The frontend should no longer display "waiting for admin review" immediately after an organization creates a campaign or published post.

Default create behavior is:

```text
Campaign -> active
Organization post -> published
```

### Image replacement

For campaign and organization post update endpoints, providing new image files replaces the current image set. The frontend should preserve the existing images visually until the user intentionally selects replacement images.

### Brief endpoints

Brief endpoints intentionally return no pagination metadata. Their response is a simple `data` array.

### Fresh database note

These backend changes modified base migrations directly because the project is not in production and is intended to be reset with:

```bash
php artisan migrate:fresh --seed
```
