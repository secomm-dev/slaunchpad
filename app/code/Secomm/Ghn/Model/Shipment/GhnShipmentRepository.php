<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Magento\Framework\App\ResourceConnection;

/**
 * TASK-9Q5ZAK (GHN-D, SPEC §20) — persistence for secomm_ghn_shipment. One row per shipment
 * attempt; the row is written PENDING BEFORE the Create Order call so a provider success that
 * is followed by a local failure always leaves a resumable anchor (resubmit the same
 * client_order_code — sandbox-proven idempotency — never blind re-creation).
 *
 * Status transitions: PENDING → SUBMITTED | FAILED | UNKNOWN. SUBMITTED is terminal for the
 * create path (webhook/cancel own it later); UNKNOWN marks an uncertain result needing
 * reconciliation. Re-running the create on a SUBMITTED row is a no-op by contract.
 */
class GhnShipmentRepository
{
    public const TABLE = 'secomm_ghn_shipment';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_UNKNOWN = 'UNKNOWN';

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByShipmentId(int $shipmentId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resourceConnection->getTableName(self::TABLE))
                ->where('magento_shipment_id = ?', $shipmentId)
                ->order('entity_id DESC')
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByClientOrderCode(string $clientOrderCode): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resourceConnection->getTableName(self::TABLE))
                ->where('client_order_code = ?', $clientOrderCode)
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * Resolves a provider order code (webhook identity) back to the create anchor row — used by
     * the E1 webhook for safe provider-order lookup/audit. The lifecycle Track matching itself
     * stays in the ShippingCore processor (carrier_code + track_number).
     *
     * @return array<string, mixed>|null
     */
    public function findByGhnOrderCode(string $ghnOrderCode): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resourceConnection->getTableName(self::TABLE))
                ->where('ghn_order_code = ?', $ghnOrderCode)
                ->order('entity_id DESC')
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * Writes the PENDING anchor row. A duplicate client_order_code (concurrent double trigger)
     * surfaces as the connection's duplicate-key exception — callers treat it as "someone else
     * is already creating this shipment" and exit silently.
     *
     * @param array<string, mixed> $row full column set (client_order_code, magento_order_id,
     *        magento_shipment_id, shop_id, shipping_reference, provider_status = PENDING, ...)
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
     * Provider accepted the create — anchor the tracking identity.
     */
    public function markSubmitted(
        string $clientOrderCode,
        string $ghnOrderCode,
        ?int $serviceTypeId,
        ?float $totalFee,
        ?string $expectedDeliveryAt
    ): void {
        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName(self::TABLE),
            [
                'provider_status' => self::STATUS_SUBMITTED,
                'ghn_order_code' => $ghnOrderCode,
                'service_type_id' => $serviceTypeId,
                'provider_reason_code' => null,
                'actual_fee' => $totalFee,
                'expected_delivery_at' => $expectedDeliveryAt,
            ],
            ['client_order_code = ?' => $clientOrderCode, 'provider_status <> ?' => self::STATUS_SUBMITTED]
        );
    }

    /**
     * Provider rejected (FAILED — deterministic, safe to inspect) or the result is uncertain
     * (UNKNOWN — timeout / 5xx / malformed after the POST; reconciled by resubmission).
     */
    public function markNotSubmitted(string $clientOrderCode, string $status, string $reasonToken): void
    {
        if ($status !== self::STATUS_FAILED && $status !== self::STATUS_UNKNOWN) {
            throw new \InvalidArgumentException('Only FAILED or UNKNOWN may close a non-submitted attempt.');
        }

        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName(self::TABLE),
            ['provider_status' => $status, 'provider_reason_code' => $reasonToken],
            ['client_order_code = ?' => $clientOrderCode]
        );
    }
}
