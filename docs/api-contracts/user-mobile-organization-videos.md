# User / Mobile Frontend API Contract — Organization Videos

This contract is for the **public user/mobile application**. Users only consume organization videos; upload and management belong to the admin/company platforms.

## Mobile feature

Add a **Videos** section/tab to an organization/public publisher profile.

The mobile UI should support:

- load a paginated list of organization videos
- show an empty state when an organization has no videos
- play a selected video from the API-provided URL
- load additional pages as the user scrolls
- open/share a video details route if the product needs a dedicated screen

Only videos belonging to an **active organization** are publicly available.

---

## Authentication

These endpoints are public and do not require a bearer token.

They are under the existing mobile discovery throttle.

Base path:

`/api/mobile/discovery/organizations/{organizationId}/videos`

---

## Video type

```ts
export type OrganizationVideo = {
  id: string;
  model: "organization";
  modelId: string;
  prop: "videos";
  url: string;
  originalName: string;
  mimeType: string | null;
  size: number;
  position: number;
  createdAt: string | null;
  updatedAt: string | null;
};
```

The player must use `video.url` as returned. Do not construct a storage path in the app.

---

# List organization videos

`GET /api/mobile/discovery/organizations/{organizationId}/videos?page=1&perPage=20`

Query params:

| Param | Type | Default | Notes |
| --- | --- | --- | --- |
| `page` | integer | `1` | Standard pagination page |
| `perPage` | integer | `20` | Clamped by the API to `1..100` |

Videos are ordered by media position and then creation time.

Response shape:

```json
{
  "success": true,
  "message": "Organization videos retrieved successfully.",
  "data": [
    {
      "id": "video-uuid",
      "model": "organization",
      "modelId": "organization-uuid",
      "prop": "videos",
      "url": "https://.../company-intro.mp4",
      "originalName": "company-intro.mp4",
      "mimeType": "video/mp4",
      "size": 20971520,
      "position": 0,
      "createdAt": "2026-08-24T12:00:00+00:00",
      "updatedAt": "2026-08-24T12:00:00+00:00"
    }
  ],
  "error": null,
  "meta": {
    "currentPage": 1,
    "perPage": 20,
    "total": 1,
    "lastPage": 1
  }
}
```

Pagination rule:

```ts
const hasNextPage = response.meta.currentPage < response.meta.lastPage;
const nextPage = hasNextPage ? response.meta.currentPage + 1 : undefined;
```

Do not infer the last page from `data.length`; use `meta.lastPage`.

---

# Show one organization video

`GET /api/mobile/discovery/organizations/{organizationId}/videos/{videoId}`

Response:

```json
{
  "success": true,
  "message": "Organization video retrieved successfully.",
  "data": {
    "id": "video-uuid",
    "model": "organization",
    "modelId": "organization-uuid",
    "prop": "videos",
    "url": "https://.../company-intro.mp4",
    "originalName": "company-intro.mp4",
    "mimeType": "video/mp4",
    "size": 20971520,
    "position": 0,
    "createdAt": "2026-08-24T12:00:00+00:00",
    "updatedAt": "2026-08-24T12:00:00+00:00"
  },
  "error": null,
  "meta": {}
}
```

---

# Error response

Example not found response:

```json
{
  "success": false,
  "message": "The requested video could not be found.",
  "data": null,
  "error": {
    "code": "not_found",
    "message": "The requested video could not be found.",
    "details": null
  },
  "meta": {}
}
```

Expected statuses:

- `404`: organization is not public/active, organization does not exist, or video does not belong to it
- `429`: mobile discovery throttle exceeded

---

# Organization profile integration

Recommended profile UI:

```text
OrganizationProfile
 ├─ Header / organization info
 ├─ Posts
 ├─ Campaigns
 └─ Videos
     ├─ VideoGrid / VideoList
     └─ VideoPlayer
```

Recommended behavior:

1. Do not block the organization profile while videos load.
2. Lazy-load the videos endpoint when the Videos tab is opened, or prefetch after the main profile data succeeds.
3. If `data` is empty, show an empty state such as **No videos yet**.
4. Keep pagination independent from posts/campaign pagination.
5. Cache by `organizationId` and page.
6. When a video is selected, pass the returned URL directly to the platform video player.

---

# Suggested query keys

For React Query / TanStack Query style clients:

```ts
const organizationVideosKey = (organizationId: string) =>
  ["organization", organizationId, "videos"] as const;

const organizationVideoKey = (organizationId: string, videoId: string) =>
  ["organization", organizationId, "videos", videoId] as const;
```

For infinite pagination:

```ts
async function getOrganizationVideos(organizationId: string, page = 1) {
  return api.get(
    `/api/mobile/discovery/organizations/${organizationId}/videos`,
    { params: { page, perPage: 20 } },
  );
}
```

---

# Playback guidance

The backend currently returns the original uploaded video URL. The frontend should:

- use native/platform controls unless the product has a custom player
- support MP4, MOV, and WebM according to the target platform capabilities
- show a loading indicator while the player buffers
- handle playback errors without breaking the whole organization profile
- avoid downloading every video eagerly; load video bytes when playback/preview requires them

There is currently no guaranteed poster/thumbnail field. Use a neutral video placeholder until thumbnail generation is added later.

---

# Important separation from admin/company upload APIs

The user/mobile app must **not** call admin or company resumable upload endpoints.

Upload session fields such as `progressPercent`, `missingChunks`, pause, resume, and complete are management-platform concerns only. The user app only consumes finalized media returned by the public discovery API.
