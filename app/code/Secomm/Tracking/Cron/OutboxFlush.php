<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Cron;

use Psr\Log\LoggerInterface;
use Secomm\Tracking\Model\Delivery\FlushService;

/**
 * FEAT-31X6N2 / TASK-VRKJKQ — every-minute outbox flush (crontab.xml).
 * FlushService exits before any DB work when the master switch is off.
 */
class OutboxFlush
{
    public function __construct(
        private readonly FlushService $flushService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->flushService->execute();
        } catch (\Throwable $e) {
            // Cron errors must surface in logs but never kill the scheduler run.
            $this->logger->error('Secomm Tracking: outbox flush failed', ['error' => $e->getMessage()]);
        }
    }
}
