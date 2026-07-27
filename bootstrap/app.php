<?php

use App\Support\Mobile\MobileApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/mobile/*')) {
                return MobileApiResponse::error('validation_error', $exception->getMessage(), $exception->errors(), 422);
            }
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/mobile/*')) {
                return MobileApiResponse::error('unauthenticated', $exception->getMessage(), null, 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if ($request->is('api/mobile/*')) {
                return MobileApiResponse::error(
                    'forbidden',
                    $exception->getMessage() !== '' ? $exception->getMessage() : 'This action is unauthorized.',
                    null,
                    403,
                );
            }
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/mobile/*')) {
                return MobileApiResponse::error('not_found', 'Not found.', null, 404);
            }
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/mobile/*')) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                return MobileApiResponse::error(
                    'http_error',
                    $exception->getMessage() !== '' ? $exception->getMessage() : 'Request could not be processed.',
                    null,
                    $exception->getStatusCode(),
                );
            }

            return MobileApiResponse::error('server_error', 'Server error.', null, 500);
        });
    })->create();
