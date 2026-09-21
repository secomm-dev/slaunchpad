<?php
/**
 * Repository for MoMo payment attempts.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollectionFactory;
use Secomm\MoMo\Model\ResourceModel\PaymentAttemptResource;

class PaymentAttemptRepository implements PaymentAttemptRepositoryInterface
{
    /**
     * PaymentAttemptRepository constructor.
     *
     * @param PaymentAttemptFactory $factory
     * @param PaymentAttemptCollectionFactory $collectionFactory
     * @param ResourceConnection $resourceConnection
     * @param DateTime $dateTime
     * @param PaymentAttemptResource $resource
     */
    public function __construct(
        private readonly PaymentAttemptFactory $factory,
        private readonly PaymentAttemptCollectionFactory $collectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly DateTime $dateTime,
        private readonly PaymentAttemptResource $resource
    ) {
    }

    /**
     * @inheritdoc
     */
    public function save(PaymentAttemptInterface $attempt): PaymentAttemptInterface
    {
        /** @var PaymentAttempt $attempt */
        $this->resource->save($attempt);

        return $attempt;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $entityId): PaymentAttemptInterface
    {
        $attempt = $this->factory->create();
        $this->resource->load($attempt, $entityId);
        if (!$attempt->getEntityId()) {
            throw new NoSuchEntityException(
                __('MoMo payment attempt with id "%1" does not exist.', $entityId)
            );
        }

        return $attempt;
    }

    /**
     * @inheritdoc
     */
    public function getByOrderRef(string $orderRef): ?PaymentAttemptInterface
    {
        if ($orderRef === '') {
            return null;
        }

        return $this->fetchOne('order_ref', $orderRef);
    }

    /**
     * @inheritdoc
     */
    public function lockByOrderRef(string $orderRef): ?PaymentAttemptInterface
    {
        if ($orderRef === '') {
            return null;
        }
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(PaymentAttemptResource::TABLE), 'entity_id')
            ->where('order_ref = ?', $orderRef)
            ->forUpdate(true);
        $entityId = $connection->fetchOne($select);
        if ($entityId === false || $entityId === null) {
            return null;
        }

        return $this->getById((int)$entityId);
    }

    /**
     * @inheritdoc
     */
    public function getActiveByQuoteId(int $quoteId): ?PaymentAttemptInterface
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(PaymentAttemptResource::TABLE), 'entity_id')
            ->where('quote_id = ?', $quoteId)
            ->where(
                'payment_status IN (?)',
                [PaymentAttemptInterface::STATUS_INITIATED, PaymentAttemptInterface::STATUS_ACTIVE]
            )
            ->order('entity_id DESC')
            ->limit(1);
        $entityId = $connection->fetchOne($select);

        return $entityId ? $this->getById((int)$entityId) : null;
    }

    /**
     * @inheritdoc
     */
    public function getBlockingAttemptByQuoteId(int $quoteId): ?PaymentAttemptInterface
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(PaymentAttemptResource::TABLE), 'entity_id')
            ->where('quote_id = ?', $quoteId)
            ->where(
                'payment_status IN (?) OR requires_reconciliation = 1',
                [PaymentAttemptInterface::STATUS_PAID, PaymentAttemptInterface::STATUS_FINALIZED]
            )
            ->order('entity_id DESC')
            ->limit(1);
        $entityId = $connection->fetchOne($select);

        return $entityId ? $this->getById((int)$entityId) : null;
    }

    /**
     * @inheritdoc
     */
    public function getListByQuoteId(int $quoteId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PaymentAttemptInterface::QUOTE_ID, $quoteId);
        $collection->setOrder(PaymentAttemptInterface::ENTITY_ID);

        return array_values($collection->getItems());
    }

    /**
     * @inheritdoc
     */
    public function claimEmailDispatch(int $entityId, int $token, int $grace): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $reclaimBefore = $this->dateTime->timestamp() - $grace;
        $affected = $connection->update(
            $this->resourceConnection->getTableName(PaymentAttemptResource::TABLE),
            [PaymentAttemptInterface::EMAIL_DISPATCH => $token],
            [
                'entity_id = ?' => $entityId,
                'email_dispatch IS NULL OR email_dispatch = 0 OR email_dispatch <= ?' => $reclaimBefore,
            ]
        );

        return (int)$affected > 0;
    }

    /**
     * @inheritdoc
     */
    public function releaseEmailDispatch(int $entityId, int $token): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $affected = $connection->update(
            $this->resourceConnection->getTableName(PaymentAttemptResource::TABLE),
            [PaymentAttemptInterface::EMAIL_DISPATCH => null],
            ['entity_id = ?' => $entityId, 'email_dispatch = ?' => $token]
        );

        return (int)$affected > 0;
    }

    /**
     * Fetch a single attempt by an indexed field (null when none).
     *
     * @param string $field
     * @param string $value
     * @return PaymentAttemptInterface|null
     */
    private function fetchOne(string $field, string $value): ?PaymentAttemptInterface
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(PaymentAttemptResource::TABLE), 'entity_id')
            ->where($field . ' = ?', $value)
            ->limit(1);
        $entityId = $connection->fetchOne($select);

        return $entityId ? $this->getById((int)$entityId) : null;
    }
}
