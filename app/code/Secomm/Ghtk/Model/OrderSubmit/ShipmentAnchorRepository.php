<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\OrderSubmit;

use Magento\Framework\App\ResourceConnection;

/**
 * TASK-DFGFZ9 phase 2 (DEC-TASKDFGFZ9-002), COD wording amended by phase 3 (DEC-TASKDFGFZ9-003)
 * — persistence for secomm_ghtk_shipment. One row per submit attempt; the row is written
 * PENDING BEFORE the submit POST (parity with Secomm_Ghn) so a success followed by a local
 * failure always leaves a resumable anchor. The table holds PROVIDER FACTS only
 * (label/tracking/weight/idempotency; `cod_amount` stays written for audit, never read):
 * the frozen amount and the one-collection rule live in the Secomm_Cod ledger
 * (`secomm_cod_collection`).
 *
 * Status transitions: PENDING → SUBMITTED | RECOVERED | FAILED | UNKNOWN. RECOVERED marks the
 * ORDER_ID_EXIST path (the provider order already existed; identity validated by
 * {@see OrderSubmitService::validateDuplicate()}); UNKNOWN = uncertain transport result
 * (timeout / 5xx / malformed / identity conflict) needing reconciliation.
 *
 * Keyed by partner_order_code (`ghtk-{increment_id}-{seq}` — deterministic): `magento_shipment_id`
 * is always NULL today because the submit runs BEFORE the shipment save.
 */
class ShipmentAnchorRepository
{
    public const TABLE = 'secomm_ghtk_shipment';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_RECOVERED = 'RECOVERED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_UNKNOWN = 'UNKNOWN';

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPartnerCode(string $partnerOrderCode): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resourceConnection->getTableName(self::TABLE))
                ->where('partner_order_code = ?', $partnerOrderCode)
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * Writes the PENDING anchor row. A duplicate partner_order_code (concurrent double submit)
     * surfaces as the connection's duplicate-key exception and aborts the submit.
     *
     * @param array<string, mixed> $row full column set; provider_status forced to PENDING
     */
    public function insertPending(array $row): void
    {
        $row['provider_status'] = self::STATUS_PENDING;
        $this->resourceConnection->getConnection()->insert(
            $this->resourceConnection->getTableName(self::TABLE),
            $row
        );
    }

    /**
     * Provider accepted the submit (fresh or via ORDER_ID_EXIST recovery) — anchor the label
     * identity. Guarded: a SUBMITTED row is never overwritten (RECOVERED may follow PENDING).
     */
    public function markSubmitted(
        string $partnerOrderCode,
        string $labelId,
        string $trackingNumber,
        bool $recovered
    ): void {
        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName(self::TABLE),
            [
                'provider_status' => $recovered ? self::STATUS_RECOVERED : self::STATUS_SUBMITTED,
                'label_id' => $labelId,
                'tracking_number' => $trackingNumber,
                'provider_reason_code' => null,
            ],
            ['partner_order_code = ?' => $partnerOrderCode, 'provider_status <> ?' => self::STATUS_SUBMITTED]
        );
    }

    /**
     * Provider rejected (FAILED — deterministic, no provider order) or the result is uncertain
     * (UNKNOWN — timeout / 5xx / malformed / identity conflict; reconciled by resubmitting the
     * same partner code).
     */
    public function markNotSubmitted(string $partnerOrderCode, string $status, string $reasonToken): void
    {
        if ($status !== self::STATUS_FAILED && $status !== self::STATUS_UNKNOWN) {
            throw new \InvalidArgumentException('Only FAILED or UNKNOWN may close a non-submitted attempt.');
        }

        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName(self::TABLE),
            ['provider_status' => $status, 'provider_reason_code' => $reasonToken],
            ['partner_order_code = ?' => $partnerOrderCode, 'provider_status <> ?' => self::STATUS_SUBMITTED]
        );
    }
}
