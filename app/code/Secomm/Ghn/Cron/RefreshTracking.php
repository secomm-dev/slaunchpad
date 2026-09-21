<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Cron;

use Psr\Log\LoggerInterface;
use Secomm\Ghn\Model\Tracking\TrackingRefreshService;

/**
 * TASK-GKHXY1 (GHN-E1) — cron wrapper: heals missed webhooks for non-terminal GHN shipments.
 * The webhook remains the primary source; this only runs when the merchant opts in.
 */
class RefreshTracking
{
    public function __construct(
        private readonly TrackingRefreshService $refreshService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $this->refreshService->execute();
        } catch (\Throwable $exception) {
            // A reconciliation failure must never break the cron schedule.
            $this->logger->error('GHN tracking refresh cron failed.', ['exception' => $exception->getMessage()]);
        }
    }
}
