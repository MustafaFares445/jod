<?php

declare(strict_types=1);

return [
    'ffmpeg_binary' => env('VIDEO_FFMPEG_BINARY', 'ffmpeg'),

    'preview' => [
        'enabled' => filter_var(env('VIDEO_PREVIEW_ENABLED', true), FILTER_VALIDATE_BOOL),
        'duration_seconds' => max(1, (int) env('VIDEO_PREVIEW_DURATION_SECONDS', 3)),
        'height' => max(144, (int) env('VIDEO_PREVIEW_HEIGHT', 480)),
        'crf' => min(51, max(0, (int) env('VIDEO_PREVIEW_CRF', 28))),
        'preset' => env('VIDEO_PREVIEW_PRESET', 'veryfast'),
        'process_timeout_seconds' => max(10, (int) env('VIDEO_PREVIEW_PROCESS_TIMEOUT_SECONDS', 45)),
    ],
];
