# Frontend media-manager integration handoff

The frontend should use the shared media API for every organization logo, campaign image, and organization post image upload. Files must not be embedded in normal model create/update bodies.

## Shared frontend service

Add one `mediaServices` client with these operations:

- `upload(model, modelId, prop, file)` -> `POST /media/{model}/{modelId}/{prop}`
- `replace(model, modelId, prop, mediaId, file)` -> `POST /media/{model}/{modelId}/{prop}/{mediaId}/replace`
- `remove(model, modelId, prop, mediaId)` -> `DELETE /media/{model}/{modelId}/{prop}/{mediaId}`

The API client already provides the `/api/v1` base, so frontend service paths should start at `/media/...`.

Every upload/replace request creates a `FormData`, appends exactly one field named `file`, and lets Axios/browser set the multipart boundary automatically.

## Campaign flow

Create:
1. submit campaign JSON to `/org/campaigns`
2. read `response.data.id`
3. upload selected images sequentially with `model=campaign`, `prop=images`

Edit:
- add -> upload
- replace -> use the selected `media[].id`
- remove -> delete with the selected `media[].id`

Continue rendering `images: string[]`; use `media: MediaItem[]` for management actions.

## Organization post flow

Create:
1. submit post JSON to `/org/posts`
2. read `response.data.id`
3. upload selected images sequentially with `model=post`, `prop=images`

Edit uses the same add/replace/delete behavior as campaigns.

## Organization settings logo

Profile data is updated separately through `/org/settings/profile`.

- no logo -> upload `organization/{organizationId}/logo`
- existing logo -> replace using `profile.logo.id`
- remove -> delete using `profile.logo.id`

The profile keeps `image` as a display URL and exposes `logo` as the full media object.

## Registration logo

Registration no longer accepts a file. Register the organization first, store the returned access token, read `data.user.organizationId`, then upload the optional logo through `organization/{organizationId}/logo`.

## Current frontend repository note

At the time of this change, `latifamho/JOD-FrontEnd` `main` contains no `type="file"` inputs, so there are no existing live media body uploads to replace. New file selectors should call only the shared media service described here.

The current registration form is also stale against the registration field contract introduced before this media-manager change (PR #41), so those registration fields should be aligned before wiring a logo selector.
