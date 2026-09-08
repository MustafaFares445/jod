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
            ->subject('JOD password change verification code')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Use the following verification code to change your JOD account password:')
            ->line($this->code)
            ->line("This code expires in {$this->expiresInMinutes} minutes.")
            ->line('If you did not request a password change, you can ignore this message.');
    }
}
