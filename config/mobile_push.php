<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('MOBILE_PUSH_ENABLED', false),
    'provider' => env('MOBILE_PUSH_PROVIDER', 'fcm'),

    'fcm' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'endpoint' => 'https://fcm.googleapis.com/v1/projects/%s/messages:send',
    ],
];
