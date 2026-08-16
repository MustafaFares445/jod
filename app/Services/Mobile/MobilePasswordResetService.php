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
    public function canDeliverTo(string $login): bool
    {
        if ($this->isEmailLogin($login)) {
            return true;
        }

        if (app()->environment('testing')) {
            return true;
        }

        return filled(config('mobile_auth.password_reset.webhook_url'));
    }

    public function issue(User $user, string $login): bool
    {
        $code = $this->generateCode();
        $expiresMinutes = max(1, (int) config('mobile_auth.password_reset.expires_minutes', 15));
        $codeHash = $this->hashCode($code);

        DB::table('mobile_password_reset_codes')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code_hash' => $codeHash,
                'attempts' => 0,
                'expires_at' => now()->addMinutes($expiresMinutes),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Preserve the legacy row for rollout compatibility without storing the
        // newly issued mobile code in plaintext.
        if (filled($user->email)) {
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => $codeHash,
                    'created_at' => now(),
                ],
            );
        }

        if ($this->deliver($user, $login, $code, $expiresMinutes)) {
            return true;
        }

        $this->consume($user);

        return false;
    }

    public function verify(User $user, string $code): bool
    {
        $record = DB::table('mobile_password_reset_codes')
            ->where('user_id', $user->id)
            ->first();

        if ($record === null) {
            return $this->verifyLegacyCode($user, $code);
        }

        if (now()->greaterThan($record->expires_at)) {
            $this->consume($user);

            return false;
        }

        $maxAttempts = max(1, (int) config('mobile_auth.password_reset.max_attempts', 5));
        if ((int) $record->attempts >= $maxAttempts) {
            $this->consume($user);

            return false;
        }

        if (hash_equals((string) $record->code_hash, $this->hashCode($code))) {
            return true;
        }

        $attempts = (int) $record->attempts + 1;
        if ($attempts >= $maxAttempts) {
            $this->consume($user);
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

        if (filled($user->email)) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        }
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

    private function verifyLegacyCode(User $user, string $code): bool
    {
        if (strlen($code) !== 6 || ! filled($user->email)) {
            return false;
        }

        $record = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        if ($record === null || ! isset($record->created_at)) {
            return false;
        }

        if (now()->diffInMinutes($record->created_at) > 15) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            return false;
        }

        return hash_equals((string) $record->token, $code);
    }

    private function deliver(User $user, string $login, string $code, int $expiresMinutes): bool
    {
        if (! $this->isEmailLogin($login)) {
            return $this->deliverToPhone($user, $code, $expiresMinutes);
        }

        return $this->deliverToEmail($user, $code, $expiresMinutes);
    }

    private function deliverToPhone(User $user, string $code, int $expiresMinutes): bool
    {
        if (! filled($user->phone)) {
            return false;
        }

        $webhookUrl = config('mobile_auth.password_reset.webhook_url');
        if (! filled($webhookUrl)) {
            return app()->environment('testing');
        }

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

    private function deliverToEmail(User $user, string $code, int $expiresMinutes): bool
    {
        if (! filled($user->email)) {
            return false;
        }

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

    private function isEmailLogin(string $login): bool
    {
        return filter_var($login, FILTER_VALIDATE_EMAIL) !== false;
    }
}
