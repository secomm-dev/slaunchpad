<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Tracking;

use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Logger\GhnLogger;

/**
 * TASK-GKHXY1 (GHN-E1) — thin opt-in shell around the shared reconciliation service (mirror of
 * the Ghtk precedent): the webhook is the PRIMARY tracking source; this refresh only heals
 * missed webhooks for non-terminal GHN shipments, when the merchant enables it.
 */
class TrackingRefreshService
{
    private const DEFAULT_THRESHOLD_HOURS = 6;

    public function __construct(
        private readonly \Secomm\ShippingCore\Model\Tracking\TrackingReconciliationService $reconciliationService,
        private readonly Config $config,
        private readonly GhnLogger $logger
    ) {
    }

    /**
     * @return int number of shipment states re-synced (0 when disabled)
     */
    public function execute(?int $storeId = null): int
    {
        if (!$this->config->isTrackingRefreshEnabled($storeId)) {
            return 0;
        }

        $thresholdHours = $this->config->getTrackingRefreshThresholdHours($storeId);
        $synced = $this->reconciliationService->refresh(
            ($thresholdHours > 0 ? $thresholdHours : self::DEFAULT_THRESHOLD_HOURS) * 3600
        );
        $this->logger->call('GHN tracking refresh finished', ['synced' => $synced]);

        return $synced;
    }
}
