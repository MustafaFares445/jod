<?php

use App\Http\Middleware\EnsureAccessToken;
use App\Http\Middleware\EnsureApiResponseMessage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'access-token' => EnsureAccessToken::class,
        ]);

        $middleware->api(append: [
            EnsureApiResponseMessage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API exceptions keep Laravel's JSON payload and are normalized by the API middleware.
    })->create();
