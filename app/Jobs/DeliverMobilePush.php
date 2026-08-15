<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MobilePushGateway;
use App\Models\MobilePushDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class DeliverMobilePush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $deliveryId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(MobilePushGateway $gateway): void
    {
        $delivery = MobilePushDelivery::query()
            ->with(['notification', 'device'])
            ->find($this->deliveryId);

        if ($delivery === null || in_array($delivery->status, ['sent', 'stale'], true)) {
            return;
        }

        $notification = $delivery->notification;
        $device = $delivery->device;

        if ($notification === null || $device === null) {
            $delivery->update([
                'status' => 'stale',
                'last_error' => null,
            ]);

            return;
        }

        if ((string) $device->user_id !== (string) $notification->recipient_id) {
            $delivery->update([
                'status' => 'stale',
                'last_error' => 'Push delivery no longer belongs to the notification recipient.',
            ]);

            return;
        }

        $delivery->increment('attempts');

        try {
            $result = $gateway->send($device, $notification);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'last_error' => Str::limit($exception->getMessage(), 1000, ''),
            ]);

            throw $exception;
        }

        if ($result->isStale()) {
            $delivery->update([
                'status' => 'stale',
                'provider_message_id' => null,
                'last_error' => null,
            ]);
            $device->delete();

            return;
        }

        $delivery->update([
            'status' => 'sent',
            'provider_message_id' => $result->providerMessageId,
            'last_error' => null,
            'sent_at' => now(),
        ]);
    }
}
