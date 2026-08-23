# General Media Manager API Contract

This contract replaces model-specific file uploads in organization registration/settings, campaigns, and organization posts.

## Goals

- Model create/update requests contain model data only.
- Every media mutation uses one shared API.
- Every upload/replace request accepts exactly one file.
- Media is addressed by a stable `mediaId` for replace/delete.
- The target is identified by `model`, `modelId`, and `prop`.

## Base path

`/api/v1/media`

All media endpoints require the normal authenticated access token.

The media routes intentionally do **not** use `org-active` middleware. This allows a newly registered organization in `pending` state to upload its logo immediately after registration returns `user.organizationId`. Authorization still verifies that the authenticated user may update the target model.

## Target enums

| model | allowed prop | max items | notes |
|---|---|---:|---|
| `organization` | `logo` | 1 | singleton; upload again returns validation error, use replace or delete |
| `campaign` | `images` | 10 | upload one image per request |
| `post` | `images` | 10 | upload one image per request |

`modelId` is the UUID of the target model.

## File validation

The multipart field is always named `file`.

- required file
- image
- MIME/extensions: `jpg`, `jpeg`, `png`, `webp`
- max size: 5 MB per file

Do not send `logo`, `image`, `images`, or `images[]` inside organization/campaign/post create or update bodies.

---

## 1. Upload media

```http
POST /api/v1/media/{model}/{modelId}/{prop}
Content-Type: multipart/form-data
Authorization: Bearer <access-token>
```

### Path params

- `model`: `organization | campaign | post`
- `modelId`: target UUID
- `prop`: valid prop for the selected model (`logo` or `images`)

### Form data

```text
file: <binary file>
```

### Success — 201

```json
{
  "data": {
    "id": "MEDIA_UUID",
    "model": "campaign",
    "modelId": "CAMPAIGN_UUID",
    "prop": "images",
    "url": "https://api.example.test/storage/media/campaign/...",
    "originalName": "cover.webp",
    "mimeType": "image/webp",
    "size": 183420,
    "position": 0,
    "createdAt": "2026-08-23T13:30:00+00:00",
    "updatedAt": "2026-08-23T13:30:00+00:00"
  }
}
```

For `organization/logo`, if a logo already exists, upload returns `422`; use replace with the existing media ID.

For `campaign/images` and `post/images`, upload appends the image until the collection reaches 10 items.

For multiple images, call this endpoint once per file. Upload sequentially if frontend order must match file selection order.

---

## 2. Replace media

```http
POST /api/v1/media/{model}/{modelId}/{prop}/{mediaId}/replace
Content-Type: multipart/form-data
Authorization: Bearer <access-token>
```

### Path params

- `model`: `organization | campaign | post`
- `modelId`: target UUID
- `prop`: target prop
- `mediaId`: media UUID returned by upload/model response

### Form data

```text
file: <binary replacement file>
```

### Success — 200

Returns the same media contract as upload.

The existing `mediaId`, target, prop, and position are preserved. The old physical file is deleted after the new media record has been updated successfully.

A `mediaId` cannot be used across another model/modelId/prop. Scoped mismatches return `404`.

---

## 3. Delete media

```http
DELETE /api/v1/media/{model}/{modelId}/{prop}/{mediaId}
Authorization: Bearer <access-token>
```

### Success — 204

No response body.

For multi-item props, remaining media positions are compacted after deletion.

---

## Media object

```ts
export type MediaModel = 'organization' | 'campaign' | 'post'
export type MediaProp = 'logo' | 'images'

export interface MediaItem {
  id: string
  model: MediaModel
  modelId: string
  prop: MediaProp
  url: string
  originalName: string
  mimeType: string | null
  size: number
  position: number
  createdAt: string | null
  updatedAt: string | null
}
```

## Model response compatibility

### Organization settings profile

The profile keeps `image` as a display URL and adds `logo` with the media ID needed by replace/delete.

```json
{
  "data": {
    "id": "ORG_UUID",
    "companyName": "Example Org",
    "image": "https://.../logo.webp",
    "logo": {
      "id": "MEDIA_UUID",
      "model": "organization",
      "modelId": "ORG_UUID",
      "prop": "logo",
      "url": "https://.../logo.webp",
      "position": 0
    }
  }
}
```

`logo` is `null` when no logo is uploaded.

### Campaign and organization post resources

Both keep `images: string[]` for display compatibility and add `media: MediaItem[]` for media management.

```json
{
  "data": {
    "id": "MODEL_UUID",
    "images": ["https://.../one.webp", "https://.../two.webp"],
    "media": [
      {
        "id": "MEDIA_1_UUID",
        "model": "campaign",
        "modelId": "MODEL_UUID",
        "prop": "images",
        "url": "https://.../one.webp",
        "position": 0
      }
    ]
  }
}
```

---

# Changes to normal model endpoints

## Organization registration

```http
POST /api/v1/company/auth/register
```

Registration is now model data only. Do **not** send `image` or `logo`.

After successful registration:

1. Store the returned access token as usual.
2. Read `data.user.organizationId`.
3. If the user selected a logo, call:
   `POST /api/v1/media/organization/{organizationId}/logo` with multipart `file`.

The logo upload is a second request because an organization ID does not exist before registration.

## Organization settings

```http
PATCH /api/v1/org/settings/profile
```

Do not send `image` or `logo`. Update profile data first, then upload/replace/delete `organization/{organizationId}/logo` through the media manager.

## Campaign create/update

```http
POST  /api/v1/org/campaigns
PATCH /api/v1/org/campaigns/{campaignId}
```

Do not send `images` in these bodies.

Create flow:

1. Create campaign with JSON model fields.
2. Read `data.id`.
3. Upload each selected file using `campaign/{id}/images`.

Edit flow:

- add image -> upload
- change one image -> replace using its `mediaId`
- remove one image -> delete using its `mediaId`

## Organization post create/update

```http
POST  /api/v1/org/posts
PATCH /api/v1/org/posts/{postId}
```

Do not send `images` in these bodies. Use the same two-stage flow with `post/{id}/images`.

---

# Frontend shared service

Use one reusable service for every file upload.

```ts
import { api } from '@/services/api'

export type MediaModel = 'organization' | 'campaign' | 'post'
export type MediaProp = 'logo' | 'images'

export interface MediaItem {
  id: string
  model: MediaModel
  modelId: string
  prop: MediaProp
  url: string
  originalName: string
  mimeType: string | null
  size: number
  position: number
  createdAt: string | null
  updatedAt: string | null
}

interface MediaResponse {
  data: MediaItem
}

const target = (model: MediaModel, modelId: string, prop: MediaProp) =>
  `/media/${model}/${modelId}/${prop}`

export const mediaServices = {
  async upload(model: MediaModel, modelId: string, prop: MediaProp, file: File) {
    const formData = new FormData()
    formData.append('file', file)

    const response = await api.post<MediaResponse>(target(model, modelId, prop), formData)
    return response.data.data
  },

  async replace(
    model: MediaModel,
    modelId: string,
    prop: MediaProp,
    mediaId: string,
    file: File,
  ) {
    const formData = new FormData()
    formData.append('file', file)

    const response = await api.post<MediaResponse>(
      `${target(model, modelId, prop)}/${mediaId}/replace`,
      formData,
    )
    return response.data.data
  },

  async remove(model: MediaModel, modelId: string, prop: MediaProp, mediaId: string) {
    await api.delete(`${target(model, modelId, prop)}/${mediaId}`)
  },
}
```

Do not manually set the multipart `Content-Type`; let the browser/Axios set the boundary.

## Upload helper for multiple campaign/post images

```ts
export async function uploadMediaFiles(
  model: 'campaign' | 'post',
  modelId: string,
  files: File[],
) {
  const result: MediaItem[] = []

  for (const file of files) {
    result.push(await mediaServices.upload(model, modelId, 'images', file))
  }

  return result
}
```

---

# Authorization and errors

- `401`: missing/invalid access token.
- `403`: authenticated user cannot update the target model.
- `404`: target model or scoped media ID not found.
- `422`: invalid model prop, invalid file, unsupported MIME, file too large, or prop item limit reached.

Authorization maps to the existing model permissions:

- organization logo -> organization settings update permission / organization owner
- campaign images -> campaign update permission / organization owner
- post images -> post update permission / organization owner

# Frontend migration checklist

- Add shared `mediaServices`.
- Remove `File`, `image`, `logo`, and `images` from normal API request DTOs.
- Organization registration: register first, then upload `organization/{organizationId}/logo`.
- Organization settings: update fields separately; upload/replace/delete logo through media service.
- Campaign create: create first, then upload each image.
- Campaign edit: use `media[].id` for replace/delete.
- Post create: create first, then upload each image.
- Post edit: use `media[].id` for replace/delete.
- Continue using `images` URL arrays for rendering where convenient.
- Use the returned `MediaItem` for management actions.
