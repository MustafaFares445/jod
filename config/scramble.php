<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Only routes under /api/v1 are included in generated frontend API docs.
     */
    'api_path' => 'api/v1',

    /*
     * Keep null so Scramble uses the current app domain unless API_DOMAIN is set.
     */
    'api_domain' => env('API_DOMAIN'),

    /*
     * Default export path used by `php artisan scramble:export`.
     */
    'export_path' => 'public/api.json',

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),

        'description' => <<<'MARKDOWN'
# JOD Dashboard API

This documentation is generated from the Laravel API routes, controllers, request validation, and resources.

## Frontend integration rules

- Base path: `/api/v1`
- Authentication: Laravel Sanctum bearer token.
- Send `Accept: application/json` for every request.
- Send `Content-Type: application/json` for JSON request bodies.
- Use `Authorization: Bearer {token}` for every endpoint except `POST /auth/login`.
- Use camelCase fields in frontend payloads and responses.
- Use `page`, `perPage`, `filter.*`, and `sort` for list screens.
- Validation errors return HTTP `422` with an `errors` object keyed by field name.

## Important frontend URLs

- API docs UI: `/docs/api`
- OpenAPI JSON: `/docs/api.json`
- Exported static spec: `/public/api.json` after running `composer api-docs`
MARKDOWN,
    ],

    'ui' => [
        'title' => 'JOD Dashboard API',
        'theme' => 'light',
        'hide_try_it' => false,
        'hide_schemas' => false,
        'logo' => '',
        'try_it_credentials_policy' => 'include',
        'layout' => 'responsive',
    ],

    /*
     * Explicit server list shown in the OpenAPI document.
     */
    'servers' => [
        'Current host' => 'api/v1',
        'Configured API URL' => env('API_BASE_URL', 'http://localhost/api/v1'),
    ],

    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy' => false,
    'flatten_deep_query_parameters' => true,

    /*
     * By default Scramble restricts docs outside local env. Set SCRAMBLE_ALLOW_PRODUCTION_DOCS=true
     * only when the frontend team should access generated docs on a non-local environment.
     */
    'allow_production_docs' => env('SCRAMBLE_ALLOW_PRODUCTION_DOCS', false),

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
];
