<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MobilePasswordResetService
{
    public function issue(User $user): bool
    {
        $code = $this->generateCode();
        $expiresMinutes = max(1, (int) config('mobile_auth.password_reset.expires_minutes', 15));

        DB::table('mobile_password_reset_codes')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code_hash' => $this->hashCode($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes($expiresMinutes),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        if ($this->deliver($user, $code, $expiresMinutes)) {
            return true;
        }

        DB::table('mobile_password_reset_codes')->where('user_id', $user->id)->delete();

        return false;
    }

    public function verify(User $user, string $code): bool
    {
        $record = DB::table('mobile_password_reset_codes')
            ->where('user_id', $user->id)
            ->first();

        if ($record === null) {
            return false;
        }

        if (now()->greaterThan($record->expires_at)) {
            DB::table('mobile_password_reset_codes')->where('user_id', $user->id)->delete();

            return false;
        }

        $maxAttempts = max(1, (int) config('mobile_auth.password_reset.max_attempts', 5));
        if ((int) $record->attempts >= $maxAttempts) {
            DB::table('mobile_password_reset_codes')->where('user_id', $user->id)->delete();

            return false;
        }

        if (hash_equals((string) $record->code_hash, $this->hashCode($code))) {
            return true;
        }

        $attempts = (int) $record->attempts + 1;
        if ($attempts >= $maxAttempts) {
            DB::table('mobile_password_reset_codes')->where('user_id', $user->id)->delete();
        } else {
            DB::table('mobile_password_reset_codes')
                ->where('user_id', $user->id)
                ->update([
                    'attempts' => $attempts,
                    'updated_at' => now(),
                ]);
        }

        return false;
    }

    public function consume(User $user): void
    {
        DB::table('mobile_password_reset_codes')->where('user_id', $user->id)->delete();
    }

    private function generateCode(): string
    {
        $length = max(4, (int) config('mobile_auth.password_reset.code_length', 4));
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function deliver(User $user, string $code, int $expiresMinutes): bool
    {
        if (filled($user->phone)) {
            $webhookUrl = config('mobile_auth.password_reset.webhook_url');

            if (filled($webhookUrl)) {
                try {
                    $request = Http::asJson()
                        ->timeout(max(1, (int) config('mobile_auth.password_reset.webhook_timeout_seconds', 5)));

                    if (filled($token = config('mobile_auth.password_reset.webhook_token'))) {
                        $request = $request->withToken((string) $token);
                    }

                    return $request->post((string) $webhookUrl, [
                        'phone' => $user->phone,
                        'code' => $code,
                        'purpose' => 'password_reset',
                        'expiresInMinutes' => $expiresMinutes,
                    ])->successful();
                } catch (Throwable) {
                    return false;
                }
            }
        }

        if (filled($user->email)) {
            try {
                Mail::raw(
                    "Your JOD password reset code is {$code}. It expires in {$expiresMinutes} minutes.",
                    static function ($message) use ($user): void {
                        $message->to((string) $user->email)
                            ->subject('JOD password reset code');
                    },
                );

                return true;
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }
}
