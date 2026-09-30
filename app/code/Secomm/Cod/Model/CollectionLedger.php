<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\DuplicateException;
use Secomm\Cod\Api\CodCollectionAttemptInterface;
use Secomm\Cod\Api\CodCollectionLedgerInterface;

/**
 * TASK-DFGFZ9 phase 3 (DEC-TASKDFGFZ9-003), claim closure (DEC-TASKDFGFZ9-004) — SQL
 * implementation of the COD collection ledger (`secomm_cod_collection`, owned by Secomm_Cod).
 *
 * The per-order claim is ENGINE-ENFORCED: every live row (PENDING | UNKNOWN | SUBMITTED |
 * RECOVERED, amount > 0) carries `active_order_claim = magento_order_id` under a UNIQUE
 * index — at most one attempt per order can hold it. `recordPending` is INSERT-first: a
 * concurrent different-attempt insert for the same order loses on 1062 and receives
 * {@see CodClaimConflictException}. `markNotSubmitted(FAILED)` releases the claim (NULL) —
 * a definitive provider rejection collected nothing; UNKNOWN keeps it (result uncertain).
 *
 * Thin SQL by house convention — the claim semantics are proven against the real DB with
 * two concurrent connections in `.ai/evidence/TASK-DFGFZ9/phase3-closure.md`, and unit
 * tested with a mocked connection.
 */
final class CollectionLedger implements CodCollectionLedgerInterface
{
    public const TABLE = 'secomm_cod_collection';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_RECOVERED = 'RECOVERED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_UNKNOWN = 'UNKNOWN';

    /** Statuses that may be closed out by markNotSubmitted. */
    private const CLOSEABLE_STATUSES = [self::STATUS_PENDING, self::STATUS_FAILED, self::STATUS_UNKNOWN];

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * @inheritDoc
     * INSERT-first: the UNIQUE active_order_claim index makes the per-order claim atomic —
     * a concurrent different-attempt insert for the same order loses on 1062 and receives
     * {@see CodClaimConflictException}. Same-reference duplicates re-arm (never downgrading
     * a submitted outcome; a re-arm restores PENDING so the attempt is coherent again).
     */
    public function recordPending(
        CodCollectionAttemptInterface $attempt,
        int $orderId,
        float $amount,
        string $currency
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);
        $key = [
            'carrier_code' => $attempt->getCarrierCode(),
            'provider_reference' => $attempt->getProviderShipmentReference(),
        ];

        try {
            // The UNIQUE active_order_claim index decides the contest at the engine level.
            $connection->insert($table, $key + [
                'magento_order_id' => $orderId,
                'active_order_claim' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => self::STATUS_PENDING,
                'reason_code' => null,
            ]);

            return;
        } catch (DuplicateException $duplicate) {
            // 1062 may come from the (carrier, reference) key OR the per-order claim —
            // discriminate by looking for our own key.
        }

        $existingStatus = $connection->fetchOne(
            $connection->select()->from($table, ['status'])->where('carrier_code = ?', $key['carrier_code'])
                ->where('provider_reference = ?', $key['provider_reference'])
        );

        if ($existingStatus === false || $existingStatus === null) {
            // Our key is absent → the duplicate was the CLAIM: another (carrier, reference)
            // holds this order's active claim.
            throw $this->claimConflict($connection, $table, $orderId);
        }

        if ($existingStatus === self::STATUS_SUBMITTED || $existingStatus === self::STATUS_RECOVERED) {
            return; // never downgrade a submitted outcome
        }

        try {
            // Same-attempt re-arm: refresh and restore PENDING (FAILED/PENDING/UNKNOWN rows
            // re-arm; SUBMITTED never reaches here).
            $connection->update(
                $table,
                [
                    'magento_order_id' => $orderId,
                    'active_order_claim' => $orderId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => self::STATUS_PENDING,
                    'reason_code' => null,
                ],
                $key + ['status <> ?' => self::STATUS_SUBMITTED]
            );
        } catch (DuplicateException $e) {
            // A different attempt claimed the order between our duplicate and the re-arm.
            throw $this->claimConflict($connection, $table, $orderId);
        }
    }

    /**
     * Builds the conflict exception, naming the current holder when it is visible.
     */
    private function claimConflict($connection, string $table, int $orderId): CodClaimConflictException
    {
        $holder = $connection->fetchRow(
            $connection->select()
                ->from($table, ['carrier_code', 'provider_reference'])
                ->where('active_order_claim = ?', $orderId)
                ->order('entity_id ASC')
        );
        $detail = is_array($holder) && $holder !== []
            ? sprintf('held by %s shipment "%s"', $holder['carrier_code'], $holder['provider_reference'])
            : 'held by another shipment attempt (holder not visible — retry after reconciling)';

        return new CodClaimConflictException(__(
            'Order #%1 already has an active COD collection claim (%2) — P1 collects COD once per order.',
            (string) $orderId,
            $detail
        ));
    }

    /**
     * @inheritDoc
     */
    public function markSubmitted(CodCollectionAttemptInterface $attempt, bool $recovered): void
    {
        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName(self::TABLE),
            [
                'status' => $recovered ? self::STATUS_RECOVERED : self::STATUS_SUBMITTED,
                'reason_code' => null,
            ],
            [
                'carrier_code = ?' => $attempt->getCarrierCode(),
                'provider_reference = ?' => $attempt->getProviderShipmentReference(),
                'status <> ?' => self::STATUS_SUBMITTED,
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function markNotSubmitted(CodCollectionAttemptInterface $attempt, string $status, string $reasonCode): void
    {
        if ($status !== self::STATUS_FAILED && $status !== self::STATUS_UNKNOWN) {
            throw new \InvalidArgumentException('Only FAILED or UNKNOWN may close a non-submitted attempt.');
        }

        $data = ['status' => $status, 'reason_code' => $reasonCode];
        if ($status === self::STATUS_FAILED) {
            // A definitive provider rejection collected nothing — RELEASE the order's claim.
            $data['active_order_claim'] = null;
        }
        // UNKNOWN keeps the claim: the provider order may exist; the slot stays held until
        // the attempt is reconciled (same-reference retry) or resolved by an operator.

        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName(self::TABLE),
            $data,
            [
                'carrier_code = ?' => $attempt->getCarrierCode(),
                'provider_reference = ?' => $attempt->getProviderShipmentReference(),
                'status NOT IN (?)' => [self::STATUS_SUBMITTED, self::STATUS_RECOVERED],
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function findFrozenAmount(CodCollectionAttemptInterface $attempt): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resourceConnection->getTableName(self::TABLE), ['amount', 'currency'])
                ->where('carrier_code = ?', $attempt->getCarrierCode())
                ->where('provider_reference = ?', $attempt->getProviderShipmentReference())
                ->where('amount > 0')
                ->where('status <> ?', self::STATUS_FAILED)
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    /**
     * @inheritDoc
     */
    public function findCollectedPrior(int $orderId, ?CodCollectionAttemptInterface $excludeAttempt): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName(self::TABLE),
                ['carrier_code', 'provider_reference', 'amount', 'currency']
            )
            ->where('magento_order_id = ?', $orderId)
            ->where('amount > 0')
            ->where('status <> ?', self::STATUS_FAILED);

        if ($excludeAttempt !== null) {
            // Exclude ONLY this attempt's own row — every other (carrier, reference) blocks.
            // (Zend where() binds a single placeholder, so quote the pair explicitly.)
            $select->where(
                sprintf(
                    'NOT (carrier_code = %s AND provider_reference = %s)',
                    $connection->quoteInto('?', $excludeAttempt->getCarrierCode()),
                    $connection->quoteInto('?', $excludeAttempt->getProviderShipmentReference())
                )
            );
        }

        $row = $connection->fetchRow($select->order('entity_id ASC'));

        return is_array($row) && $row !== [] ? $row : null;
    }
}
