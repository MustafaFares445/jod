# Admin Frontend API Contract — Organization Videos

This contract is for the **admin dashboard**. Admins manage videos for a selected organization.

## Frontend feature

Add a **Videos** section/tab inside the organization details screen.

The admin UI should support:

- list organization videos
- preview/play a video
- add a new video using resumable streaming upload
- pause an upload
- resume an upload after pause, refresh, or network loss
- show server-authoritative upload progress
- replace an existing video using the same resumable upload flow
- cancel an unfinished upload
- delete a completed video

Organizations can have at most **10 videos**, including non-expired new-video upload sessions that are still in progress.

Supported video types:

- `video/mp4`
- `video/quicktime` (`.mov`)
- `video/webm`

Maximum video size: **100 MB**.

The server chooses the chunk size. The frontend must use the returned `chunkSize` and must not hard-code its own size.

---

## Authentication

All admin endpoints require the normal admin bearer token used by `/api/v1/admin/*`.

Base resource path:

`/api/v1/admin/organizations/{organizationId}/videos`

---

## Completed video resource

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

Use `url` exactly as returned by the API for playback. Do not build a storage URL in the frontend.

---

## Upload session resource

```ts
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

`progressPercent`, `receivedChunks`, `missingChunks`, and `uploadedBytes` come from the server and are the recovery source of truth.

---

# Completed video CRUD

## List videos

`GET /api/v1/admin/organizations/{organizationId}/videos`

Response:

```json
{
  "data": [
    {
      "id": "video-uuid",
      "model": "organization",
      "modelId": "organization-uuid",
      "prop": "videos",
      "url": "https://.../video.mp4",
      "originalName": "intro.mp4",
      "mimeType": "video/mp4",
      "size": 18874368,
      "position": 0,
      "createdAt": "2026-08-24T12:00:00+00:00",
      "updatedAt": "2026-08-24T12:00:00+00:00"
    }
  ]
}
```

## Get one video

`GET /api/v1/admin/organizations/{organizationId}/videos/{videoId}`

## Delete video

`DELETE /api/v1/admin/organizations/{organizationId}/videos/{videoId}`

Success: `204 No Content`.

Creation and replacement are performed through the resumable upload endpoints below.

---

# Resumable create flow

## 1. Initiate upload

`POST /api/v1/admin/organizations/{organizationId}/videos/uploads`

JSON body:

```json
{
  "originalName": "company-intro.mp4",
  "mimeType": "video/mp4",
  "totalSize": 52428800
}
```

Response status: `201`.

Example response:

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

Persist at least these values while the upload is active:

```ts
{
  uploadId: string;
  organizationId: string;
  localFileName: string;
  localFileSize: number;
  localFileLastModified: number;
}
```

A browser cannot restore the actual `File` object after a full browser restart. If the session still exists but the file object is gone, ask the admin to choose the same file again, verify name + size, fetch upload status, then continue only the missing chunks.

---

## 2. Stream a chunk

`PUT /api/v1/admin/organizations/{organizationId}/videos/uploads/{uploadId}/chunks/{chunkIndex}`

Request body is the **raw binary chunk**, not `multipart/form-data`.

Header:

`Content-Type: application/octet-stream`

Chunk calculation:

```ts
const start = chunkIndex * session.chunkSize;
const end = Math.min(start + session.chunkSize, file.size);
const blob = file.slice(start, end);
```

Send `blob` as the request body.

Every non-final chunk must contain exactly `chunkSize` bytes. The final chunk must contain exactly the remaining bytes.

The response is the updated `VideoUploadSession` resource.

The same chunk index is idempotent after the server has accepted it. If the frontend is unsure whether a chunk completed, call the status endpoint before retrying.

### Recommended browser implementation

Use `XMLHttpRequest` or an HTTP client that exposes upload progress for the currently active chunk.

```ts
function uploadChunk(url: string, blob: Blob, token: string, onProgress: (loaded: number) => void) {
  return new Promise<void>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("PUT", url);
    xhr.setRequestHeader("Authorization", `Bearer ${token}`);
    xhr.setRequestHeader("Content-Type", "application/octet-stream");

    xhr.upload.onprogress = event => {
      if (event.lengthComputable) onProgress(event.loaded);
    };

    xhr.onload = () => xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(xhr);
    xhr.onerror = () => reject(xhr);
    xhr.onabort = () => reject(new DOMException("Upload aborted", "AbortError"));
    xhr.send(blob);
  });
}
```

For the UI while one chunk is in-flight:

```ts
const optimisticBytes = Math.min(
  session.totalSize,
  session.uploadedBytes + currentChunkLoadedBytes,
);
const optimisticPercent = (optimisticBytes / session.totalSize) * 100;
```

After the chunk request completes, replace optimistic progress with the new server response.

---

## 3. Read progress / recover after refresh

`GET /api/v1/admin/organizations/{organizationId}/videos/uploads/{uploadId}`

Use this endpoint:

- after page refresh
- after network reconnect
- when a chunk request result is uncertain
- before resuming a persisted upload
- to reconcile frontend progress with the server

Only resend indexes returned in `missingChunks`.

---

## 4. Pause

First stop scheduling new chunks and abort the current XHR if needed. After that request has settled, call:

`POST /api/v1/admin/organizations/{organizationId}/videos/uploads/{uploadId}/pause`

The returned status is `paused`.

Do not send chunk requests while the session is paused.

---

## 5. Resume

`POST /api/v1/admin/organizations/{organizationId}/videos/uploads/{uploadId}/resume`

Use the returned `missingChunks` list and continue those indexes only.

Do not assume the next missing chunk is the last chunk attempted by the browser; always trust the API.

---

## 6. Finalize

When `canComplete === true`:

`POST /api/v1/admin/organizations/{organizationId}/videos/uploads/{uploadId}/complete`

Response:

```json
{
  "data": {
    "upload": {
      "id": "upload-uuid",
      "status": "completed",
      "progressPercent": 100,
      "videoId": "video-uuid"
    },
    "video": {
      "id": "video-uuid",
      "model": "organization",
      "modelId": "organization-uuid",
      "prop": "videos",
      "url": "https://.../video.mp4"
    }
  }
}
```

While finalization is running, show a state such as **Processing video…** because the upload can temporarily have status `assembling` after all bytes reached 100%.

After success:

1. clear persisted upload state
2. add/replace the returned `video` in the admin cache
3. refetch the video list if ordering matters

---

## 7. Cancel unfinished upload

`DELETE /api/v1/admin/organizations/{organizationId}/videos/uploads/{uploadId}`

Success: `204 No Content`.

Cancel removes temporary chunk files. It does not delete a completed video.

---

# Resumable replace flow

Replacement uses the same endpoints. The only difference is the initiation body:

```json
{
  "originalName": "new-intro.webm",
  "mimeType": "video/webm",
  "totalSize": 41943040,
  "replaceVideoId": "existing-video-uuid"
}
```

Do not delete the existing video before the replacement is complete. The old completed video remains available until the final `complete` call succeeds.

---

# Frontend state machine

Recommended states:

```ts
type VideoUploadUiState =
  | "idle"
  | "initiating"
  | "uploading"
  | "pausing"
  | "paused"
  | "resuming"
  | "assembling"
  | "completed"
  | "failed"
  | "cancelled";
```

Rules:

1. `initiating` -> create session.
2. `uploading` -> send `missingChunks` sequentially or with a very small concurrency limit.
3. `pausing` -> stop the queue, abort the active request, then persist `paused` from the API.
4. `paused` -> no chunk requests.
5. `resuming` -> call resume, then rebuild the queue from `missingChunks`.
6. when all chunks are accepted -> call complete.
7. `assembling` -> disable pause/cancel UI until complete returns.
8. after refresh -> fetch session status first, then decide whether to ask the user to reselect the file.

A concurrency of **1 chunk at a time** is the safest first implementation and gives predictable pause behavior.

---

# Error handling

Expected categories:

- `401`: token missing/expired
- `403`: admin lacks organization permission
- `404`: organization, video, or upload session does not exist
- `422`: invalid MIME/size, video limit reached, wrong chunk size/index, paused/expired session, incomplete finalize

For `422`, display the API validation message and keep the upload session unless the user explicitly cancels it.

If a network request fails, do not mark its chunk as uploaded locally. Fetch upload status and continue from `missingChunks`.
