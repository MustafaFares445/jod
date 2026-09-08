<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountVerificationCodeNotification extends Notification
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
            ->subject('Verify your JOD account')
            ->markdown('mail.verification-code', [
                'heading' => 'Verify your JOD account',
                'recipientName' => (string) $notifiable->name,
                'intro' => 'Use the following verification code to activate your JOD account:',
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'securityMessage' => 'If you did not create this account, you can safely ignore this message.',
            ]);
    }
}
