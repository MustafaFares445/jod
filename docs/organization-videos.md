# Organization Videos

Organizations can own up to 10 public videos. Videos reuse the existing `media` table with:

- `model_type = organization`
- `model_id = <organization id>`
- `prop = videos`

Supported upload formats are `mp4`, `mov`, and `webm`, with a maximum file size of 100 MB per video.

## Organization dashboard API

All endpoints require the normal organization authentication and `org-active` middleware. Access is scoped to the authenticated organization.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/v1/org/videos` | List the authenticated organization's videos |
| POST | `/api/v1/org/videos` | Upload a video (`multipart/form-data`, field: `file`) |
| GET | `/api/v1/org/videos/{video}` | Get one owned video |
| PATCH/PUT | `/api/v1/org/videos/{video}` | Replace an owned video (`multipart/form-data`, field: `file`) |
| DELETE | `/api/v1/org/videos/{video}` | Delete an owned video |

The existing generic media endpoint also supports the `videos` prop for organizations:

`POST /api/v1/media/organization/{organizationId}/videos`

## Public mobile API

These endpoints do not require authentication and are covered by the mobile discovery throttle.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/mobile/discovery/organizations/{organization}/videos` | Paginated public organization videos |
| GET | `/api/mobile/discovery/organizations/{organization}/videos/{video}` | Public video details |

The list endpoint accepts `page` and `perPage` using the standard Laravel/mobile pagination contract.

Each video uses the existing `MediaResource` contract:

- `id`
- `model`
- `modelId`
- `prop`
- `url`
- `originalName`
- `mimeType`
- `size`
- `position`
- `createdAt`
- `updatedAt`

## System implementation plan

### Organization dashboard

1. Add a **Videos** section under organization profile/content management.
2. Show current videos in a grid/list with playback preview, filename, size, and upload date.
3. Add upload with client-side validation for MP4/MOV/WebM and 100 MB maximum size.
4. Add replace and delete actions with loading/error states.
5. Display the 10-video limit and disable upload when the limit is reached.
6. Use the returned `url` for preview/playback; do not construct storage URLs on the frontend.

### Mobile app

1. Add a Videos tab/section to organization/public publisher profiles.
2. Fetch `/api/mobile/discovery/organizations/{organization}/videos` only when the Videos section is opened or prefetched.
3. Render video thumbnails/placeholders and open a player using the returned `url`.
4. Paginate with the API metadata instead of loading all videos at once.
5. Handle an empty video list without hiding the rest of the organization profile.

### Follow-up enhancements

- Generate server-side thumbnails/posters for videos.
- Store duration, width, height, and transcoding status as metadata if playback requirements grow.
- Move large uploads to direct/object-storage multipart uploads if 100 MB application-server uploads become a bottleneck.
- Add moderation/status fields if organization videos later require admin review.
- Add explicit video ordering/reordering if dashboard users need manual ordering beyond upload order.
