<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Tracking;

use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\ShippingCore\Model\Tracking\ShipmentTrackingProcessor;

/**
 * TASK-PWHG0V (GHN-E3-B) — immediate per-order reconcile through the E1 pipeline
 * (fetcher → SAME GhnStatusMapper → ShippingCore processor). Used after an admin Cancel/Return
 * SUCCESS so the normalized state reflects the provider action without waiting for the webhook
 * (dev/sandbox stores often cannot receive callbacks).
 *
 * NOT a second lifecycle path: it calls the exact E1 chain; the processor stays the only writer
 * of `secomm_carrier_tracking_state` (brief §21). Failures are swallowed by callers — the webhook
 * remains the authoritative source and a failed reconcile never breaks the admin flow.
 */
class ShipmentReconciler
{
    public function __construct(
        private readonly GhnTrackingFetcher $fetcher,
        private readonly ShipmentTrackingProcessor $processor,
        private readonly GhnLogger $logger
    ) {
    }

    /**
     * @return bool true when the pipeline applied (or safely skipped) a fresh observation
     */
    public function reconcileByOrderCode(string $orderCode): bool
    {
        $orderCode = trim($orderCode);
        if ($orderCode === '') {
            return false;
        }

        try {
            $update = $this->fetcher->fetch($orderCode);
            if ($update === null) {
                return false;
            }

            return $this->processor->process($update);
        } catch (\Exception $failure) {
            // Reconciliation is best-effort; provider/runtime Exceptions are non-fatal (the
            // webhook is the eventual lifecycle truth). PHP Errors/TypeErrors must remain
            // visible — they are never swallowed here.
            $this->logger->warning('GHN reconcile after admin action failed (non-fatal)', [
                'order_code' => $orderCode,
                'reason' => $failure->getMessage(),
            ]);

            return false;
        }
    }
}
