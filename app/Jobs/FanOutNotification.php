<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\NotificationDistributionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FanOutNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $notificationId,
        public readonly string $distributionBatchId,
    ) {}

    public function handle(NotificationDistributionService $service): void
    {
        $service->fanOut($this->notificationId, $this->distributionBatchId);
    }
}
