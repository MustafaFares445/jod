<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\BrandedTestEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailTestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! (bool) config('mail.test_endpoint_enabled')) {
            return $this->errorResponse('Not found.', 404);
        }

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'message' => ['sometimes', 'string', 'max:5000'],
        ]);

        $recipient = $validated['email'];
        $subject = $validated['subject'] ?? 'JOD email test';
        $body = $validated['message'] ?? 'JOD email delivery is configured correctly.';
        $mailer = (string) config('mail.default');
        $mailerConfig = (array) config("mail.mailers.{$mailer}", []);

        $diagnostics = [
            'deliveryMode' => 'synchronous',
            'mailer' => $mailer,
            'host' => $mailerConfig['host'] ?? null,
            'port' => $mailerConfig['port'] ?? null,
            'scheme' => $mailerConfig['scheme'] ?? null,
            'username' => $mailerConfig['username'] ?? null,
            'fromAddress' => config('mail.from.address'),
            'recipient' => $recipient,
        ];

        try {
            Mail::to($recipient)->send(new BrandedTestEmail($subject, $body));
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse('Email test failed.', 502, [
                ...$diagnostics,
                'exception' => class_basename($exception),
                'smtpMessage' => $exception->getMessage(),
            ]);
        }

        return $this->successResponse(
            $diagnostics,
            'Test email sent successfully.',
        );
    }
}
