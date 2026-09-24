<?php
/**
 * Raw-SQL repository for MoMo refund requests on the independent connection.
 *
 * Deliberately NOT an AbstractDb resource model: the standard resource path
 * resolves to the same MySQL connection the CreditmemoService refund
 * transaction runs on, so any write issued during the gateway call would
 * roll back together with the creditmemo — exactly the evidence
 * (FAILED/UNKNOWN rows) this module must keep.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Api\RefundRequestRepositoryInterface;
use Secomm\MoMo\Service\RefundConnectionProvider;

class RefundRequestRepository implements RefundRequestRepositoryInterface
{
    /**
     * @var RefundConnectionProvider
     */
    private RefundConnectionProvider $connectionProvider;

    /**
     * @var RefundRequestFactory
     */
    private RefundRequestFactory $factory;

    /**
     * RefundRequestRepository constructor.
     *
     * @param RefundConnectionProvider $connectionProvider
     * @param RefundRequestFactory $factory
     */
    public function __construct(
        RefundConnectionProvider $connectionProvider,
        RefundRequestFactory $factory
    ) {
        $this->connectionProvider = $connectionProvider;
        $this->factory = $factory;
    }

    /**
     * @inheritdoc
     */
    public function insert(RefundRequestInterface $refund): RefundRequestInterface
    {
        $connection = $this->getConnection();
        $connection->insert(
            $this->getTableName(),
            [
                RefundRequestInterface::ORDER_ID => $refund->getOrderId(),
                RefundRequestInterface::ORDER_INCREMENT_ID => $refund->getOrderIncrementId(),
                RefundRequestInterface::INVOICE_ID => $refund->getInvoiceId(),
                RefundRequestInterface::MOMO_ORDER_REF => $refund->getMomoOrderRef(),
                RefundRequestInterface::MOMO_TRANS_ID => $refund->getMomoTransId(),
                RefundRequestInterface::REFUND_ORDER_ID => $refund->getRefundOrderId(),
                RefundRequestInterface::REQUEST_ID => $refund->getRequestId(),
                RefundRequestInterface::AMOUNT => $refund->getAmount(),
                RefundRequestInterface::CURRENCY => $refund->getCurrency(),
                RefundRequestInterface::STATUS => $refund->getStatus() ?: RefundRequestInterface::STATUS_PENDING,
                RefundRequestInterface::OPEN_FLAG => 1,
                RefundRequestInterface::STORE_ID => $refund->getStoreId(),
            ]
        );
        $entityId = (int)$connection->lastInsertId($this->getTableName());
        $refund->setEntityId($entityId);
        $refund->setOpenFlag(true);

        return $refund;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $entityId): ?RefundRequestInterface
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTableName())
            ->where(RefundRequestInterface::ENTITY_ID . ' = ?', $entityId)
            ->limit(1);

        return $this->hydrate($connection->fetchRow($select));
    }

    /**
     * @inheritdoc
     */
    public function getByRequestId(string $requestId): ?RefundRequestInterface
    {
        if ($requestId === '') {
            return null;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTableName())
            ->where(RefundRequestInterface::REQUEST_ID . ' = ?', $requestId)
            ->limit(1);

        return $this->hydrate($connection->fetchRow($select));
    }

    /**
     * @inheritdoc
     */
    public function findOpenByPaymentIdentity(string $momoOrderRef, string $momoTransId): ?RefundRequestInterface
    {
        if ($momoOrderRef === '' || $momoTransId === '') {
            return null;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTableName())
            ->where(RefundRequestInterface::MOMO_ORDER_REF . ' = ?', $momoOrderRef)
            ->where(RefundRequestInterface::MOMO_TRANS_ID . ' = ?', $momoTransId)
            ->where(RefundRequestInterface::OPEN_FLAG . ' = 1')
            ->order(RefundRequestInterface::ENTITY_ID . ' DESC')
            ->limit(1);

        return $this->hydrate($connection->fetchRow($select));
    }

    /**
     * @inheritdoc
     */
    public function getSuccessfulTotal(string $momoOrderRef, string $momoTransId): int
    {
        if ($momoOrderRef === '' || $momoTransId === '') {
            return 0;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(
                $this->getTableName(),
                [RefundRequestInterface::AMOUNT => 'COALESCE(SUM(' . RefundRequestInterface::AMOUNT . '), 0)']
            )
            ->where(RefundRequestInterface::MOMO_ORDER_REF . ' = ?', $momoOrderRef)
            ->where(RefundRequestInterface::MOMO_TRANS_ID . ' = ?', $momoTransId)
            ->where(RefundRequestInterface::STATUS . ' = ?', RefundRequestInterface::STATUS_SUCCESS);

        return (int)$connection->fetchOne($select);
    }

    /**
     * @inheritdoc
     */
    public function sweepStalePending(string $momoOrderRef, string $momoTransId, int $staleSeconds): int
    {
        if ($momoOrderRef === '' || $momoTransId === '' || $staleSeconds <= 0) {
            return 0;
        }
        $connection = $this->getConnection();
        $cutoff = $connection->formatDate(time() - $staleSeconds);

        return (int)$connection->update(
            $this->getTableName(),
            [
                RefundRequestInterface::STATUS => RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::CLASSIFICATION_REASON =>
                    RefundRequestInterface::REASON_STALE_PENDING_SWEEP,
                RefundRequestInterface::LAST_ERROR =>
                    'Pending refund outlived the response window; provider outcome unconfirmed.',
            ],
            [
                RefundRequestInterface::MOMO_ORDER_REF . ' = ?' => $momoOrderRef,
                RefundRequestInterface::MOMO_TRANS_ID . ' = ?' => $momoTransId,
                RefundRequestInterface::STATUS . ' = ?' => RefundRequestInterface::STATUS_PENDING,
                RefundRequestInterface::CREATED_AT . ' < ?' => $cutoff,
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function finalize(RefundRequestInterface $refund): bool
    {
        if ($refund->getEntityId() === null
            || !in_array($refund->getStatus(), [RefundRequestInterface::STATUS_SUCCESS,
                RefundRequestInterface::STATUS_FAILED], true)
        ) {
            return false;
        }

        $connection = $this->getConnection();
        $affected = $connection->update(
            $this->getTableName(),
            [
                RefundRequestInterface::STATUS => $refund->getStatus(),
                RefundRequestInterface::OPEN_FLAG => null,
                RefundRequestInterface::PROVIDER_TRANSACTION_ID => $refund->getProviderTransactionId(),
                RefundRequestInterface::RESPONSE_CODE => $refund->getResponseCode(),
                RefundRequestInterface::RESPONSE_MESSAGE => $refund->getResponseMessage(),
                RefundRequestInterface::CLASSIFICATION_REASON => $refund->getClassificationReason(),
                RefundRequestInterface::LAST_ERROR => $refund->getLastError(),
                RefundRequestInterface::RESOLVED_AT => $connection->formatDate(time()),
            ],
            [
                RefundRequestInterface::ENTITY_ID . ' = ?' => $refund->getEntityId(),
                RefundRequestInterface::OPEN_FLAG . ' = 1',
            ]
        );

        return (int)$affected > 0;
    }

    /**
     * @inheritdoc
     */
    public function markUnknown(RefundRequestInterface $refund): bool
    {
        if ($refund->getEntityId() === null) {
            return false;
        }

        $connection = $this->getConnection();
        $affected = $connection->update(
            $this->getTableName(),
            [
                RefundRequestInterface::STATUS => RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::RESPONSE_CODE => $refund->getResponseCode(),
                RefundRequestInterface::RESPONSE_MESSAGE => $refund->getResponseMessage(),
                RefundRequestInterface::CLASSIFICATION_REASON => $refund->getClassificationReason(),
                RefundRequestInterface::LAST_ERROR => $refund->getLastError(),
            ],
            [
                RefundRequestInterface::ENTITY_ID . ' = ?' => $refund->getEntityId(),
                RefundRequestInterface::STATUS . ' = ?' => RefundRequestInterface::STATUS_PENDING,
            ]
        );

        return (int)$affected > 0;
    }

    /**
     * @inheritdoc
     */
    public function markCreditmemo(int $entityId, int $creditmemoId): bool
    {
        if ($entityId <= 0 || $creditmemoId <= 0) {
            return false;
        }

        $connection = $this->getConnection();
        $affected = $connection->update(
            $this->getTableName(),
            [RefundRequestInterface::CREDITMEMO_ID => $creditmemoId],
            [
                RefundRequestInterface::ENTITY_ID . ' = ?' => $entityId,
                RefundRequestInterface::CREDITMEMO_ID . ' IS NULL',
            ]
        );

        return (int)$affected > 0;
    }

    /**
     * @inheritdoc
     */
    public function getList(?string $status = null, ?string $orderIncrementId = null, int $limit = 50): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTableName())
            ->order(RefundRequestInterface::ENTITY_ID . ' DESC')
            ->limit(max(1, $limit));
        if ($status !== null && $status !== '') {
            $select->where(RefundRequestInterface::STATUS . ' = ?', $status);
        }
        if ($orderIncrementId !== null && $orderIncrementId !== '') {
            $select->where(RefundRequestInterface::ORDER_INCREMENT_ID . ' = ?', $orderIncrementId);
        }

        $items = [];
        foreach ($connection->fetchAll($select) as $row) {
            $entity = $this->hydrate($row);
            if ($entity !== null) {
                $items[] = $entity;
            }
        }

        return $items;
    }

    /**
     * The (prefixed) refund table name.
     *
     * @return string
     */
    private function getTableName(): string
    {
        return $this->connectionProvider->getTableName('secomm_momo_refund');
    }

    /**
     * Hydrate one row into an entity (null when the row is empty).
     *
     * @param array|false $row
     * @return RefundRequestInterface|null
     */
    private function hydrate(array|bool $row): ?RefundRequestInterface
    {
        if (!is_array($row) || $row === []) {
            return null;
        }

        $entity = $this->factory->create();
        $entity->setData($row);

        return $entity;
    }

    /**
     * The independent connection accessor (shared by all methods).
     *
     * @return AdapterInterface
     */
    private function getConnection(): AdapterInterface
    {
        return $this->connectionProvider->getConnection();
    }
}
