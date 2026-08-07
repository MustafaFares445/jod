<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

final class TokenService
{
    public const ACCESS_ABILITY = 'access-api';

    public const REFRESH_ABILITY = 'refresh-token';

    private const ACCESS_TOKEN_PREFIX = 'access-token:';

    private const REFRESH_TOKEN_PREFIX = 'refresh-token:';

    /**
     * @return array<string, int|string>
     */
    public function issueTokenPair(User $user): array
    {
        return $this->createTokenPair($user, (string) Str::uuid());
    }

    /**
     * Rotate a refresh token once and revoke its previous token pair.
     *
     * @return array<string, int|string>|null
     */
    public function rotateRefreshToken(string $plainTextToken): ?array
    {
        $tokenParts = explode('|', $plainTextToken, 2);

        if (count($tokenParts) !== 2 || ! ctype_digit($tokenParts[0]) || $tokenParts[1] === '') {
            return null;
        }

        [$tokenId, $tokenSecret] = $tokenParts;

        return DB::transaction(function () use ($tokenId, $tokenSecret): ?array {
            /** @var PersonalAccessToken|null $refreshToken */
            $refreshToken = PersonalAccessToken::query()
                ->lockForUpdate()
                ->find((int) $tokenId);

            if (! $this->isValidRefreshToken($refreshToken, $tokenSecret)) {
                return null;
            }

            $user = $refreshToken->tokenable;

            if (! $user instanceof User) {
                return null;
            }

            $this->revokeTokenSession($user, $refreshToken);

            $user->forceFill([
                'last_active_at' => now(),
            ])->save();

            return $this->issueTokenPair($user);
        });
    }

    public function revokeTokenSession(User $user, PersonalAccessToken $token): void
    {
        $sessionId = $this->sessionIdFromToken($token);

        if ($sessionId === null) {
            $token->delete();

            return;
        }

        $user->tokens()
            ->whereIn('name', [
                self::ACCESS_TOKEN_PREFIX.$sessionId,
                self::REFRESH_TOKEN_PREFIX.$sessionId,
            ])
            ->delete();
    }

    /**
     * @return array<string, int|string>
     */
    private function createTokenPair(User $user, string $sessionId): array
    {
        $accessTokenLifetime = max(1, (int) config('auth_tokens.access_token_lifetime_minutes', 60));
        $refreshTokenLifetime = max(1, (int) config('auth_tokens.refresh_token_lifetime_minutes', 43200));
        $accessTokenExpiresAt = now()->addMinutes($accessTokenLifetime);
        $refreshTokenExpiresAt = now()->addMinutes($refreshTokenLifetime);

        $accessToken = $user->createToken(
            self::ACCESS_TOKEN_PREFIX.$sessionId,
            [self::ACCESS_ABILITY],
            $accessTokenExpiresAt,
        );

        $refreshToken = $user->createToken(
            self::REFRESH_TOKEN_PREFIX.$sessionId,
            [self::REFRESH_ABILITY],
            $refreshTokenExpiresAt,
        );

        return [
            'token' => $accessToken->plainTextToken,
            'refreshToken' => $refreshToken->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresIn' => $accessTokenLifetime * 60,
            'refreshExpiresIn' => $refreshTokenLifetime * 60,
            'expiresAt' => $accessTokenExpiresAt->toIso8601String(),
            'refreshExpiresAt' => $refreshTokenExpiresAt->toIso8601String(),
        ];
    }

    private function isValidRefreshToken(?PersonalAccessToken $token, string $tokenSecret): bool
    {
        if ($token === null || ! hash_equals($token->token, hash('sha256', $tokenSecret))) {
            return false;
        }

        if (! str_starts_with($token->name, self::REFRESH_TOKEN_PREFIX)) {
            return false;
        }

        if (! $token->can(self::REFRESH_ABILITY)) {
            return false;
        }

        return $token->expires_at === null || $token->expires_at->isFuture();
    }

    private function sessionIdFromToken(PersonalAccessToken $token): ?string
    {
        foreach ([self::ACCESS_TOKEN_PREFIX, self::REFRESH_TOKEN_PREFIX] as $prefix) {
            if (str_starts_with($token->name, $prefix)) {
                $sessionId = substr($token->name, strlen($prefix));

                return $sessionId !== '' ? $sessionId : null;
            }
        }

        return null;
    }
}
