<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\User;
use App\Notifications\AccountVerificationCodeNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AccountVerificationService
{
    /** @return array{sent: bool, retryAfter: int, expiresIn: int} */
    public function issue(User $user): array
    {
        $expiresInMinutes = (int) config('auth.account_verification.expire', 15);
        $cooldownSeconds = (int) config('auth.account_verification.throttle', 60);
        $record = DB::table('account_verification_tokens')->where('email', $user->email)->first();

        if ($record !== null && isset($record->last_sent_at)) {
            $lastSentAt = CarbonImmutable::parse($record->last_sent_at);
            $secondsSinceLastSend = max(0, now()->timestamp - $lastSentAt->timestamp);

            if ($secondsSinceLastSend < $cooldownSeconds) {
                return [
                    'sent' => false,
                    'retryAfter' => $cooldownSeconds - $secondsSinceLastSend,
                    'expiresIn' => $expiresInMinutes * 60,
                ];
            }
        }

        $code = (string) random_int(100000, 999999);
        $now = now();

        DB::table('account_verification_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($code),
                'attempts' => 0,
                'created_at' => $now,
                'last_sent_at' => $now,
            ],
        );

        try {
            $user->notify(new AccountVerificationCodeNotification($code, $expiresInMinutes));
        } catch (Throwable $exception) {
            if ($record === null) {
                DB::table('account_verification_tokens')->where('email', $user->email)->delete();
            } else {
                DB::table('account_verification_tokens')->updateOrInsert(
                    ['email' => $user->email],
                    [
                        'token' => $record->token,
                        'attempts' => (int) $record->attempts,
                        'created_at' => $record->created_at,
                        'last_sent_at' => $record->last_sent_at,
                    ],
                );
            }

            throw $exception;
        }

        return [
            'sent' => true,
            'retryAfter' => $cooldownSeconds,
            'expiresIn' => $expiresInMinutes * 60,
        ];
    }

    public function consume(User $user, string $code): string
    {
        $record = DB::table('account_verification_tokens')->where('email', $user->email)->first();

        if ($record === null) {
            return 'missing';
        }

        $maxAttempts = (int) config('auth.account_verification.max_attempts', 5);
        $expiresInMinutes = (int) config('auth.account_verification.expire', 15);

        if ((int) $record->attempts >= $maxAttempts) {
            DB::table('account_verification_tokens')->where('email', $user->email)->delete();

            return 'too_many_attempts';
        }

        if (! isset($record->created_at) || CarbonImmutable::parse($record->created_at)->addMinutes($expiresInMinutes)->isPast()) {
            DB::table('account_verification_tokens')->where('email', $user->email)->delete();

            return 'expired';
        }

        if (! Hash::check($code, (string) $record->token)) {
            $attempts = (int) $record->attempts + 1;

            if ($attempts >= $maxAttempts) {
                DB::table('account_verification_tokens')->where('email', $user->email)->delete();

                return 'too_many_attempts';
            }

            DB::table('account_verification_tokens')
                ->where('email', $user->email)
                ->update(['attempts' => $attempts]);

            return 'invalid';
        }

        DB::table('account_verification_tokens')->where('email', $user->email)->delete();

        return 'verified';
    }
}
