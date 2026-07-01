# JOD Dashboard Backend

Laravel API backend for the JOD dashboard.

## API Documentation

The project uses **Scramble** to generate OpenAPI documentation for the frontend team.

| Resource | URL / Command |
|---|---|
| Interactive API docs | `/docs/api` |
| OpenAPI JSON | `/docs/api.json` |
| Static OpenAPI export | `composer api-docs` |
| Frontend API guide | `API_CONTRACT_FOR_FRONTEND.md` |

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Open the API docs:

```text
http://localhost:8000/docs/api
```

## Authentication

The API uses Laravel Sanctum bearer tokens.

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

Login endpoint:

```http
POST /api/v1/auth/login
```

All other dashboard endpoints require a bearer token.

## Export API Docs

```bash
composer api-docs
```

This exports the current OpenAPI specification to:

```text
public/api.json
```

## Main API Prefixes

| Prefix | Purpose |
|---|---|
| `/api/v1/auth` | Login/logout. |
| `/api/v1/me` | Current user profile, permissions, dashboard context. |
| `/api/v1/admin` | Platform/admin dashboard endpoints. |
| `/api/v1/org` | Organization workspace endpoints. |

## Scramble Environment Variables

```env
API_VERSION=1.0.0
API_DOMAIN=
API_BASE_URL=http://localhost/api/v1
SCRAMBLE_ALLOW_PRODUCTION_DOCS=false
```

Set `SCRAMBLE_ALLOW_PRODUCTION_DOCS=true` only for a controlled staging environment where the frontend team needs access to `/docs/api`.
