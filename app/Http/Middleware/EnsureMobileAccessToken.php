<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\TokenService;
use App\Support\Mobile\MobileApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMobileAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->tokenCan(TokenService::ACCESS_ABILITY)) {
            return MobileApiResponse::error(
                'access_token_required',
                'A mobile access token is required.',
                null,
                403,
            );
        }

        return $next($request);
    }
}
