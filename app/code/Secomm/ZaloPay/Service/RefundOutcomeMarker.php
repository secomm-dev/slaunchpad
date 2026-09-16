<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

/**
 * Process-scoped marker that a provider refund for an order was ALREADY
 * requested and its outcome is already known, so the Magento core refund
 * accounting (RefundOperation -> Payment::refund -> gateway "refund"
 * command) must NOT talk to the provider a second time.
 *
 * TASK-CG6BM7 corrective round: with the pending-refund lifecycle the
 * provider interaction runs exactly ONCE per refund request — in the
 * CreditmemoRefundPlugin before the core flow (PROCESSING case) or by the
 * plugin before proceeding (SUCCESS case) — and the RefundCronjob finalize
 * runs the core accounting with the provider outcome already resolved.
 * Magento DI shares one instance per scope, so admin request AND cron worker
 * each get their own isolated marker: no state leaks between refunds.
 *
 * Keyed by order entity id — stable across the plugin and the gateway
 * command within one refund flow; different orders never collide.
 */
class RefundOutcomeMarker
{
    /**
     * @var array<int, true>
     */
    private array $skipProviderOrderIds = [];

    /**
     * Mark that the provider was already asked for this order's refund.
     *
     * @param int $orderId
     * @return void
     */
    public function markProviderAlreadyAsked(int $orderId): void
    {
        $this->skipProviderOrderIds[$orderId] = true;
    }

    /**
     * Whether the gateway refund command must skip the provider call for
     * this order (provider already asked, outcome already known).
     *
     * @param int $orderId
     * @return bool
     */
    public function isProviderAlreadyAsked(int $orderId): bool
    {
        return isset($this->skipProviderOrderIds[$orderId]);
    }
}
