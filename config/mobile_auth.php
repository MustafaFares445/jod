<?php

return [
    'password_reset' => [
        'code_length' => 4,
        'expires_minutes' => (int) env('MOBILE_RESET_CODE_EXPIRES_MINUTES', 15),
        'max_attempts' => (int) env('MOBILE_RESET_CODE_MAX_ATTEMPTS', 5),
        'webhook_url' => env('MOBILE_RESET_CODE_WEBHOOK_URL'),
        'webhook_token' => env('MOBILE_RESET_CODE_WEBHOOK_TOKEN'),
        'webhook_timeout_seconds' => (int) env('MOBILE_RESET_CODE_WEBHOOK_TIMEOUT_SECONDS', 5),
    ],
];
