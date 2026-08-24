# Mobile Public Content API Contract

Base path: `/api/mobile/discovery`

All endpoints in this document are public and do not require authentication. They are throttled with `throttle:60,1`. A bearer token may still be sent where supported to enrich viewer-specific fields, but authentication is not required to read these feeds.

## 1. Posts

### GET `/api/mobile/discovery/posts`

Returns the public posts feed used by the app home/discovery experience.

Supported query parameters are defined by `PostDiscoveryRequest` and include pagination/filtering supported by the current backend implementation.

Example response shape:

```json
{
  "success": true,
  "message": "Posts retrieved successfully.",
  "data": [],
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 0
  }
}
```

### GET `/api/mobile/discovery/posts/{post}`

Returns one public post. Returns `404` when the post is not publicly available.

---

## 2. Media

### GET `/api/mobile/discovery/media`

Returns the public media feed. At present this feed contains organization videos only, and only videos owned by active organizations are included.

Query parameters:

- `page` optional integer
- `perPage` optional integer, default `20`, maximum `100`
- `search` optional string; performs a partial `LIKE` search against video `description` and `originalName`

Each media item contains:

```json
{
  "id": "uuid",
  "model": "organization",
  "modelId": "organization-uuid",
  "prop": "videos",
  "url": "https://...",
  "originalName": "campaign-story.mp4",
  "description": "Short description shown with the video in the app.",
  "mimeType": "video/mp4",
  "size": 1234567,
  "position": 0,
  "createdAt": "2026-08-24T10:00:00+00:00",
  "updatedAt": "2026-08-24T10:00:00+00:00"
}
```

### GET `/api/mobile/discovery/media/{video}`

Returns one public video media item. Returns `404` when the video does not exist or belongs to an inactive organization.

### Video description input

Resumable organization/admin video upload initiation now accepts an optional `description` field:

```json
{
  "originalName": "campaign-story.mp4",
  "description": "Short description shown with the video in the app.",
  "mimeType": "video/mp4",
  "totalSize": 1234567,
  "replaceVideoId": null
}
```

Validation: `description` is nullable, string, maximum 5000 characters. The description is stored with the upload session and copied to the finalized video.

---

## 3. Campaigns

### GET `/api/mobile/discovery/campaigns`

Returns active public campaigns using the existing mobile campaign discovery filters and pagination.

### GET `/api/mobile/discovery/campaigns/{campaign}`

Returns one active public campaign. Returns `404` when the campaign is not publicly available.

---

## 4. Articles

### GET `/api/mobile/discovery/articles`

Returns published articles.

Query parameters:

- `page` optional integer
- `perPage` optional integer, default `20`, maximum `100`
- `search` optional string; partial `LIKE` search across `title`, `excerpt`, and `content`

Only records with `status = published` are returned. Results are ordered by `published_at` descending and then `created_at` descending.

### GET `/api/mobile/discovery/articles/{article}`

Returns one published article. Returns `404` for unpublished or missing articles.

---

## Public app feed summary

| App section | Endpoint | Public |
|---|---|---|
| Posts | `GET /api/mobile/discovery/posts` | Yes |
| Media / videos | `GET /api/mobile/discovery/media` | Yes |
| Campaigns | `GET /api/mobile/discovery/campaigns` | Yes |
| Articles | `GET /api/mobile/discovery/articles` | Yes |

The existing detail endpoints remain available for posts, campaigns, and articles, and matching media detail support is included at `GET /api/mobile/discovery/media/{video}`.
