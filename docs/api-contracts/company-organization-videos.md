# Company Frontend API Contract — Organization Videos

This contract is for the **company / organization dashboard**. Every endpoint is scoped to the organization of the authenticated company user.

## Frontend feature

Add a **Videos** section in organization profile/content management.

The company frontend should support:

- list owned videos
- preview/play a video
- add a video through resumable streaming upload
- pause an upload
- resume an upload
- recover progress after refresh or network interruption
- replace a video without removing the existing video first
- cancel an unfinished upload
- delete a completed video

Limit: **10 videos** per organization, including non-expired new-video upload sessions that are still active.

Supported types:

- `video/mp4`
- `video/quicktime` (`.mov`)
- `video/webm`

Maximum size: **100 MB**.

The backend returns the required chunk size. Always slice the selected file using `chunkSize` from the upload session.

---

## Authentication

All endpoints require the normal organization bearer token and the organization must pass the existing `org-active` middleware.

Base path:

`/api/v1/org/videos`

No organization ID is sent in these URLs. The API derives the organization from the authenticated user.

---

## Types

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

export type VideoUploadSession = {
  id: string;
  organizationId: string;
  replaceVideoId: string | null;
  videoId: string | null;
  originalName: string;
  mimeType: string;
  totalSize: number;
  chunkSize: number;
  totalChunks: number;
  receivedChunks: number[];
  missingChunks: number[];
  nextChunk: number | null;
  uploadedBytes: number;
  progressPercent: number;
  status:
    | "initiated"
    | "uploading"
    | "paused"
    | "assembling"
    | "completed"
    | "cancelled";
  canPause: boolean;
  canResume: boolean;
  canComplete: boolean;
  expiresAt: string | null;
  completedAt: string | null;
  createdAt: string | null;
  updatedAt: string | null;
};
```

---

# Completed video endpoints

## List

`GET /api/v1/org/videos`

Response:

```json
{
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
  ]
}
```

## Show

`GET /api/v1/org/videos/{videoId}`

## Delete

`DELETE /api/v1/org/videos/{videoId}`

Success: `204 No Content`.

Do not use the old generic media endpoint for organization videos. Organization videos are intentionally required to use the resumable upload flow below.

---

# Create video — resumable flow

## 1. Initiate

`POST /api/v1/org/videos/uploads`

JSON:

```json
{
  "originalName": "company-intro.mp4",
  "mimeType": "video/mp4",
  "totalSize": 52428800
}
```

Response status: `201`.

The response is a `VideoUploadSession`. Example:

```json
{
  "data": {
    "id": "upload-uuid",
    "organizationId": "organization-uuid",
    "replaceVideoId": null,
    "videoId": null,
    "originalName": "company-intro.mp4",
    "mimeType": "video/mp4",
    "totalSize": 52428800,
    "chunkSize": 5242880,
    "totalChunks": 10,
    "receivedChunks": [],
    "missingChunks": [0,1,2,3,4,5,6,7,8,9],
    "nextChunk": 0,
    "uploadedBytes": 0,
    "progressPercent": 0,
    "status": "initiated",
    "canPause": true,
    "canResume": false,
    "canComplete": false,
    "expiresAt": "2026-08-25T12:00:00+00:00",
    "completedAt": null
  }
}
```

Store `uploadId` locally while the upload is active so the upload can be recovered after a page refresh.

Recommended local metadata:

```ts
{
  uploadId: string;
  fileName: string;
  fileSize: number;
  fileLastModified: number;
}
```

After a full browser restart, JavaScript cannot restore the original `File` object. Ask the user to reselect the same file, verify filename and file size, then fetch session status and continue the missing chunks.

---

## 2. Upload one binary chunk

`PUT /api/v1/org/videos/uploads/{uploadId}/chunks/{chunkIndex}`

Body: raw binary `Blob`.

Header:

`Content-Type: application/octet-stream`

Slice the file like this:

```ts
const start = chunkIndex * session.chunkSize;
const end = Math.min(start + session.chunkSize, file.size);
const chunk = file.slice(start, end);
```

The backend verifies the byte count for every chunk. A non-final chunk must equal `chunkSize`; the final chunk must equal the exact remaining size.

The response is the updated upload session.

### UI progress

There are two useful progress values:

1. **server progress** = `session.progressPercent`
2. **temporary in-flight progress** = accepted bytes + bytes currently being sent

For an active chunk:

```ts
const uiUploadedBytes = Math.min(
  session.totalSize,
  session.uploadedBytes + activeChunkLoadedBytes,
);

const uiProgress = (uiUploadedBytes / session.totalSize) * 100;
```

After the chunk finishes, replace this local value with the API response.

Use `XMLHttpRequest`, Axios `onUploadProgress`, or another transport that exposes upload progress events for the current binary request. Plain `fetch()` does not provide standard browser upload progress events.

---

## 3. Get status / progress

`GET /api/v1/org/videos/uploads/{uploadId}`

This endpoint is the source of truth after:

- refresh
- network disconnect
- app/tab sleep
- an uncertain chunk response
- pause/resume

Only upload indexes returned in `missingChunks`.

If a chunk request fails, do not increment progress locally. Fetch status and rebuild the queue from `missingChunks`.

---

## 4. Pause

Frontend behavior:

1. stop adding new chunks to the queue
2. abort the current chunk request if the user wants an immediate pause
3. wait for that request to settle
4. call the pause API

Endpoint:

`POST /api/v1/org/videos/uploads/{uploadId}/pause`

Response status field: `paused`.

The backend rejects additional chunks while paused.

---

## 5. Resume

`POST /api/v1/org/videos/uploads/{uploadId}/resume`

Use the returned `missingChunks` array. Do not derive the resume index only from local frontend state.

The upload expiration is extended when upload activity resumes.

---

## 6. Complete

When `canComplete === true`:

`POST /api/v1/org/videos/uploads/{uploadId}/complete`

The server assembles the chunks and moves the final video into the normal media storage.

Response:

```json
{
  "data": {
    "upload": {
      "id": "upload-uuid",
      "videoId": "video-uuid",
      "progressPercent": 100,
      "status": "completed"
    },
    "video": {
      "id": "video-uuid",
      "model": "organization",
      "modelId": "organization-uuid",
      "prop": "videos",
      "url": "https://.../company-intro.mp4"
    }
  }
}
```

Display **Processing video…** while complete is running. All bytes may already show 100% while the session is being assembled.

After success:

- clear saved upload metadata
- insert/update the returned video in local query cache
- refetch `GET /api/v1/org/videos` if needed

---

## 7. Cancel upload

`DELETE /api/v1/org/videos/uploads/{uploadId}`

Success: `204 No Content`.

Cancel deletes unfinished temporary chunks. It does not delete a completed video.

---

# Replace video

Use the same initiate endpoint with `replaceVideoId`:

```json
{
  "originalName": "new-company-intro.webm",
  "mimeType": "video/webm",
  "totalSize": 36700160,
  "replaceVideoId": "existing-video-uuid"
}
```

Then use the normal chunk, pause, resume, progress, and complete endpoints.

The old video stays available until finalization succeeds. Do not optimistically remove it from the UI when replacement begins.

---

# Recommended component structure

Suggested frontend separation:

```text
OrganizationVideosPage
 ├─ VideoGrid
 │   └─ VideoCard
 ├─ VideoUploadButton
 └─ ActiveVideoUpload
     ├─ ProgressBar
     ├─ PauseButton
     ├─ ResumeButton
     └─ CancelButton
```

Recommended upload state:

```ts
type UploadState = {
  session: VideoUploadSession | null;
  file: File | null;
  activeChunk: number | null;
  activeChunkLoadedBytes: number;
  phase:
    | "idle"
    | "initiating"
    | "uploading"
    | "pausing"
    | "paused"
    | "resuming"
    | "assembling"
    | "completed"
    | "error";
};
```

Start with one chunk at a time. It gives predictable pause/resume behavior and is enough for files capped at 100 MB.

---

# Validation and errors

Expected status categories:

- `401`: auth/token issue
- `403`: organization user lacks settings update permission
- `404`: video/upload session not owned by this organization
- `422`: invalid type/size, organization at video limit, invalid chunk, paused/expired upload, incomplete finalize

Keep an existing session after recoverable `422` or network errors. Cancel it only when the user chooses Cancel or the API reports that the session has expired.
