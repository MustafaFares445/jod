<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $expiresInMinutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->theme('jod')
            ->subject('JOD password change verification code')
            ->markdown('mail.verification-code', [
                'heading' => 'Confirm your password change',
                'recipientName' => (string) $notifiable->name,
                'intro' => 'Use the following verification code to change your JOD account password:',
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'securityMessage' => 'If you did not request a password change, you can safely ignore this message.',
            ]);
    }
}
