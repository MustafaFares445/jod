<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\TokenService;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessToken
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->tokenCan(TokenService::ACCESS_ABILITY)) {
            return $this->errorResponse('An access token is required.', 403);
        }

        return $next($request);
    }
}
