# Organization Videos

Organizations can own up to 10 public videos. Finalized videos reuse the existing `media` table with:

- `model_type = organization`
- `model_id = <organization id>`
- `prop = videos`

Resumable uploads use the `media_uploads` table while chunks are still being transferred.

Supported formats:

- MP4 (`video/mp4`)
- MOV (`video/quicktime`)
- WebM (`video/webm`)

Maximum file size: **100 MB**.

The server currently returns a **5 MiB** chunk size, but frontend clients must always use the `chunkSize` returned when an upload session is initiated instead of hard-coding it.

## Resumable upload behavior

Video creation and replacement are intentionally resumable instead of one large multipart request.

The flow is:

1. initiate an upload session
2. stream raw binary chunks with numbered chunk indexes
3. read server progress at any time
4. optionally pause
5. resume from `missingChunks`
6. complete/finalize the upload
7. receive the normal `MediaResource` video

A session persists:

- total bytes
- uploaded bytes
- server progress percent
- received chunk indexes
- missing chunk indexes
- current status
- expiration time
- replacement target, when replacing an existing video

Sessions expire after 24 hours without successful continuation. Upload/chunk activity renews the expiration window.

The generic `/api/v1/media/.../videos` upload path no longer accepts video files. Organization videos must use the resumable API.

## Short feed preview processing

After the final video is stored, the backend queues `GenerateVideoPreview`. The job generates a separate lightweight MP4 asset intended for feed/autoplay previews; it does not truncate the HTTP stream by guessing byte counts.

Default processing:

- first **3 seconds**
- video only / muted preview (audio removed)
- H.264 MP4
- 480px output height
- `+faststart` MP4 layout for quick playback
- CRF 28 / `veryfast` preset
- 3 queue attempts

The media record tracks `preview_status`, `preview_disk`, `preview_path`, `preview_mime_type`, `preview_size`, and the last final `preview_error`.

Replacing a video deletes its previous preview and queues a new version. Deleting a video also deletes its preview. Preview paths include a source-version hash so stale jobs cannot overwrite a newer replacement.

Existing videos can be queued with:

```bash
php artisan videos:generate-previews
```

Use `--force` to regenerate all organization video previews.

The queue worker must be running, and the server must have the FFmpeg binary installed. Relevant environment values are `VIDEO_FFMPEG_BINARY`, `VIDEO_PREVIEW_ENABLED`, `VIDEO_PREVIEW_DURATION_SECONDS`, `VIDEO_PREVIEW_HEIGHT`, `VIDEO_PREVIEW_CRF`, `VIDEO_PREVIEW_PRESET`, and `VIDEO_PREVIEW_PROCESS_TIMEOUT_SECONDS`.

## Company API

Completed videos:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/v1/org/videos` | List the authenticated organization's videos |
| GET | `/api/v1/org/videos/{video}` | Get one owned video |
| DELETE | `/api/v1/org/videos/{video}` | Delete a completed owned video |

Resumable upload:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/v1/org/videos/uploads` | Initiate create/replace upload |
| GET | `/api/v1/org/videos/uploads/{upload}` | Get progress/status |
| PUT | `/api/v1/org/videos/uploads/{upload}/chunks/{chunk}` | Stream one raw binary chunk |
| POST | `/api/v1/org/videos/uploads/{upload}/pause` | Pause session |
| POST | `/api/v1/org/videos/uploads/{upload}/resume` | Resume session |
| POST | `/api/v1/org/videos/uploads/{upload}/complete` | Assemble/finalize the video |
| DELETE | `/api/v1/org/videos/uploads/{upload}` | Cancel unfinished session |

Replacement is initiated with `replaceVideoId` in the initiation JSON body. The old video remains available until completion succeeds.

## Admin API

Admins manage videos for a specific organization.

Completed videos:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/v1/admin/organizations/{organization}/videos` | List videos |
| GET | `/api/v1/admin/organizations/{organization}/videos/{video}` | Show video |
| DELETE | `/api/v1/admin/organizations/{organization}/videos/{video}` | Delete video |

Resumable upload:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/v1/admin/organizations/{organization}/videos/uploads` | Initiate create/replace upload |
| GET | `/api/v1/admin/organizations/{organization}/videos/uploads/{upload}` | Get progress/status |
| PUT | `/api/v1/admin/organizations/{organization}/videos/uploads/{upload}/chunks/{chunk}` | Stream one raw binary chunk |
| POST | `/api/v1/admin/organizations/{organization}/videos/uploads/{upload}/pause` | Pause session |
| POST | `/api/v1/admin/organizations/{organization}/videos/uploads/{upload}/resume` | Resume session |
| POST | `/api/v1/admin/organizations/{organization}/videos/uploads/{upload}/complete` | Assemble/finalize the video |
| DELETE | `/api/v1/admin/organizations/{organization}/videos/uploads/{upload}` | Cancel unfinished session |

## Public mobile API

These endpoints do not require authentication and are covered by the mobile discovery throttle.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/mobile/discovery/organizations/{organization}/videos` | Paginated videos for an active organization |
| GET | `/api/mobile/discovery/organizations/{organization}/videos/{video}` | Public video details |
| GET | `/api/mobile/discovery/media/{video}/preview` | Short generated feed preview |
| GET | `/api/mobile/discovery/media/{video}/stream` | Full byte-range video stream |

The list endpoint accepts `page` and `perPage` and returns the standard mobile response envelope and pagination metadata.

## Upload progress contract

The upload status resource contains:

- `id`
- `organizationId`
- `replaceVideoId`
- `videoId`
- `originalName`
- `mimeType`
- `totalSize`
- `chunkSize`
- `totalChunks`
- `receivedChunks`
- `missingChunks`
- `nextChunk`
- `uploadedBytes`
- `progressPercent`
- `status`
- `canPause`
- `canResume`
- `canComplete`
- `expiresAt`
- `completedAt`
- `createdAt`
- `updatedAt`

Chunk bodies are sent as raw `application/octet-stream`. This avoids wrapping every chunk in multipart form data.

For immediate pause behavior, the frontend should stop its queue and abort the current XHR/request before calling the pause endpoint. If the result of a chunk request is uncertain, fetch upload status and trust `missingChunks` instead of frontend-only state.

## Frontend contracts

Detailed implementation contracts are maintained separately per platform:

- `docs/api-contracts/admin-organization-videos.md`
- `docs/api-contracts/company-organization-videos.md`
- `docs/api-contracts/user-mobile-organization-videos.md`

## Current implementation notes

- Final videos use the existing public media storage and `MediaResource`.
- Media video resources expose separate `previewUrl` and `streamUrl` fields.
- Temporary chunks use the local filesystem until completion or cancellation.
- The API verifies exact chunk byte sizes before marking chunks as received.
- Re-sending an already accepted chunk index is idempotent from the upload session perspective.
- Completion verifies all chunk indexes and final assembled byte size before creating/replacing the media record.
- Organization limits include active non-expired new-video upload sessions, preventing multiple simultaneous sessions from bypassing the 10-video limit.

## Future enhancements

- server-side thumbnail/poster generation
- duration/width/height metadata
- video transcoding/adaptive bitrate streaming
- direct object-storage multipart uploads if files grow beyond the current 100 MB application-server model
- explicit video ordering/reordering
- optional moderation/status workflow if organization video review is introduced later
