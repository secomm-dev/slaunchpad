<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Shipment;

use Magento\Backend\Model\Session;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — the hand-off between a carrier's blocked online attempt
 * and the offline control on the (re-rendered) new-shipment form: the carrier gate stashes the
 * structured eligibility (reason token + human message, scalars only) when it blocks with an
 * offline-eligible reason; the offline control block pulls + clears it (per order) to prefill
 * the hidden reason fields, and the offline fulfillment observer falls back to it when the
 * posted form carries no reason.
 *
 * Carrier reports facts; ShippingCore owns the operational offline flow — hence this lives in
 * ShippingCore and the carrier only writes into it.
 */
class OfflineEligibilitySession
{
    private const SESSION_KEY = 'secomm_offline_eligibility';

    public function __construct(private readonly Session $session)
    {
    }

    /**
     * @param string $reasonCode machine reason token (e.g. INVALID_PARCEL)
     * @param string $message the human message the gate showed the admin
     */
    public function stash(int $orderId, string $reasonCode, string $message): void
    {
        if ($orderId <= 0) {
            return;
        }

        $all = $this->all();
        $all[$orderId] = ['reason_code' => $reasonCode, 'message' => $message];
        $this->session->setData(self::SESSION_KEY, $all);
    }

    /**
     * @return array{reason_code: string, message: string}|null read + CLEAR for this order
     */
    public function pull(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        $all = $this->all();
        $entry = $all[$orderId] ?? null;
        if (!is_array($entry)) {
            return null;
        }

        unset($all[$orderId]);
        $this->session->setData(self::SESSION_KEY, $all);

        return [
            'reason_code' => (string) ($entry['reason_code'] ?? ''),
            'message' => (string) ($entry['message'] ?? ''),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function all(): array
    {
        $data = $this->session->getData(self::SESSION_KEY);

        return is_array($data) ? $data : [];
    }
}
