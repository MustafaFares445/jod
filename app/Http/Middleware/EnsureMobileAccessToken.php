<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\TokenService;
use App\Support\Mobile\MobileApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMobileAccessToken
{
    public function __construct(private readonly TokenService $tokenService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();
        $hasAccessAbility = $user?->tokenCan(TokenService::ACCESS_ABILITY) ?? false;
        $hasValidBearerToken = ! $currentToken instanceof PersonalAccessToken
            || $this->tokenService->isAccessToken($currentToken);

        if (! $hasAccessAbility || ! $hasValidBearerToken) {
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
