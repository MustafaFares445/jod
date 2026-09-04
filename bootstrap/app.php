<?php

use App\Http\Middleware\EnsureAccessToken;
use App\Http\Middleware\EnsureApiResponseMessage;
use App\Http\Middleware\EnsureMobileAccessToken;
use App\Http\Middleware\EnsureOrganizationIsActive;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/api.php',
            __DIR__.'/../routes/company_auth.php',
            __DIR__.'/../routes/org_donation_workflow.php',
            __DIR__.'/../routes/mobile_account_verification.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'access-token' => EnsureAccessToken::class,
            'mobile-access-token' => EnsureMobileAccessToken::class,
            'org-active' => EnsureOrganizationIsActive::class,
        ]);

        $middleware->api(append: [
            EnsureApiResponseMessage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(static function (Request $request): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(static function (AuthenticationException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/mobile/*') && ! $request->is('api/mobile')) {
                return null;
            }

            return MobileApiResponse::error('unauthenticated', 'Unauthenticated.', null, 401);
        });

        $exceptions->render(static function (ValidationException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/mobile/*') && ! $request->is('api/mobile')) {
                return null;
            }

            return MobileApiResponse::error('validation_error', $exception->getMessage(), $exception->errors(), 422);
        });

        $exceptions->render(static function (AccessDeniedHttpException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/mobile/*') && ! $request->is('api/mobile')) {
                return null;
            }

            return MobileApiResponse::error('forbidden', 'This action is unauthorized.', null, 403);
        });

        $exceptions->render(static function (NotFoundHttpException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/mobile/*') && ! $request->is('api/mobile')) {
                return null;
            }

            return MobileApiResponse::error('not_found', 'The requested resource could not be found.', null, 404);
        });
    })->create();
