<?php

declare(strict_types=1);

return [
    'access_token_lifetime_minutes' => (int) env('AUTH_ACCESS_TOKEN_LIFETIME_MINUTES', 60),
    'refresh_token_lifetime_minutes' => (int) env('AUTH_REFRESH_TOKEN_LIFETIME_MINUTES', 43200),
];
