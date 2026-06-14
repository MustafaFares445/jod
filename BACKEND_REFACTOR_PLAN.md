# Backend Refactor Plan

## Scope

Normalize `jod-backend` around the verified API contract and the current Laravel boost rules without changing the v1 route surface or response envelopes.

## Dashboard API Gap Closure

The dashboard bootstrap surface is now aligned with the current backend:

- `GET /api/v1/me`
- `GET /api/v1/me/permissions`
- `GET /api/v1/me/dashboard-context`
- `GET /api/v1/admin/overview`
- `GET /api/v1/admin/analytics/kpis`
- `GET /api/v1/admin/analytics/weekly`
- `GET /api/v1/org/overview`
- `GET /api/v1/org/permissions/catalog`

### Contract rules

- `me` endpoints return `data` only, with no `message` field.
- `admin/overview` and analytics endpoints return `data` plus a success `message`.
- `org/overview` returns `data` only.
- Permission catalog output is sourced from the shared permission catalog, not hard-coded strings.

### Remaining normalization work

- Keep the query/filter standardization pass moving toward shared helpers.
- Keep permission strings catalog-backed across seeders, policies, tests, and Postman samples.
- Keep actor metadata flowing into services rather than resolving auth inside service methods.
