# JOD Follow System — Frontend API Contract

## Base URL
All mobile endpoints are under `/api/mobile`.

Authentication uses the existing mobile Sanctum bearer token and `mobile-access-token` middleware.

## Publisher target types
`targetType` is always one of:
- `user`
- `organization`

Laravel/PHP class names are never exposed.

## Publisher response additions
Public publisher profile responses now include:
```json
{
  "followersCount": 125,
  "isFollowing": true
}
```
For guests, `isFollowing` is `false`.

## Follow publisher
`PUT /api/mobile/publishers/{targetType}/{targetId}/follow`

Success:
```json
{
  "success": true,
  "data": {
    "targetType": "organization",
    "targetId": "uuid",
    "isFollowing": true,
    "followersCount": 126
  }
}
```
Idempotent: repeating the request does not create another relationship.

Self-follow of a user returns validation error 422. Missing/inactive targets return 404.

## Unfollow publisher
`DELETE /api/mobile/publishers/{targetType}/{targetId}/follow`

Success returns the same state payload with `isFollowing: false`. It is idempotent even when no relationship exists.

## My Following
`GET /api/mobile/me/following?type=all&page=1&perPage=20`

`type`: `all | user | organization`

Items reuse the publisher shape:
```json
{
  "id": "uuid",
  "publisherType": "user",
  "name": "Name",
  "avatarUrl": null,
  "verified": false,
  "followersCount": 12,
  "isFollowing": true
}
```

## Following Feed
`GET /api/mobile/discovery/following?page=1&perPage=20`

Each item is a typed wrapper:
```json
{
  "contentType": "post",
  "publishedAt": "2026-08-28T10:00:00+00:00",
  "content": {}
}
```
`contentType` is `post | campaign | video`. The nested `content` object reuses the existing resource contract for that content type.

Only public/published user posts, published organization posts, active organization campaigns, and organization videos are included. Follow never grants additional access.

## Organization dashboard
Existing endpoints:
- `GET /api/v1/org/overview`
- `GET /api/v1/org/dashboard/overview`

The `stats` array includes:
```json
{
  "id": "followers",
  "label": "Followers",
  "value": 1245,
  "hint": "Publisher followers"
}
```

No endpoint exposes individual follower identities in the MVP.

## Frontend behavior
Use optimistic updates for follow/unfollow, but rollback on request failure. Invalidate/refetch publisher profile, My Following, and Following Feed. Hide the follow button on the signed-in user's own publisher profile.

## Error handling
- `401`: authentication required for follow/unfollow/My Following/Following Feed.
- `404`: target unavailable.
- `422`: invalid target type, self follow, or invalid filter.
