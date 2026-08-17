<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Cron;

use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Model\Tracking\TrackingRefreshService;

/**
 * Reconciliation cron (SL-017) — delegates to TrackingRefreshService, which
 * no-ops unless tracking_refresh_enabled is set (webhook stays primary).
 */
class RefreshTracking
{
    public function __construct(
        private TrackingRefreshService $refreshService,
        private LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $synced = $this->refreshService->refresh();
            if ($synced > 0) {
                $this->logger->info('GHTK tracking reconciliation finished.', ['synced' => $synced]);
            }
        } catch (\Throwable $e) {
            // Cron must never crash on a reconciliation pass.
            $this->logger->error('GHTK tracking reconciliation failed.', ['exception' => $e->getMessage()]);
        }
    }
}
