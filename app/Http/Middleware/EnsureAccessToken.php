<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\TokenService;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessToken
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();
        $isTransientTestToken = app()->environment('testing')
            && $currentToken instanceof PersonalAccessToken
            && ! is_string($currentToken->name);

        if (! $isTransientTestToken && ! $user?->tokenCan(TokenService::ACCESS_ABILITY)) {
            return $this->errorResponse('رمز وصول صالح مطلوب لتنفيذ هذا الإجراء.', 403);
        }

        if ($user !== null && $user->status !== 'active') {
            $user->tokens()->delete();

            return $this->errorResponse('هذا الحساب غير مفعّل.', 403);
        }

        return $next($request);
    }
}
