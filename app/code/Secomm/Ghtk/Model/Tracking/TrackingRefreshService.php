<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Tracking;

use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\ShippingCore\Model\Tracking\TrackingReconciliationService;

/**
 * Tracking Status API reconciliation (SL-017 / DEC-SL017-001 §5) — THIN GHTK SHELL
 * (TASK-7AJ3K8): the carrier policy (config opt-in — the webhook stays primary — and the
 * staleness threshold) is read here, the orchestration (state query, terminal exclusion,
 * stale filter, batch, per-item continue) lives in the SHARED
 * {@see TrackingReconciliationService} wired with GhtkTrackingFetcher via DI. Never throws.
 */
class TrackingRefreshService
{
    public function __construct(
        private readonly TrackingReconciliationService $reconciliationService,
        private readonly GhtkConfig $config
    ) {
    }

    /**
     * @return int Number of states successfully re-synced.
     */
    public function refresh(): int
    {
        if (!$this->config->isTrackingRefreshEnabled()) {
            return 0;
        }

        return $this->reconciliationService->refresh(
            $this->config->getTrackingRefreshThresholdHours() * 3600
        );
    }
}
