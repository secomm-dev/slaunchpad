<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollectionFactory as CollectionFactory;

class PaymentAttemptRepository implements PaymentAttemptRepositoryInterface
{
    /**
     * PaymentAttemptRepository constructor.
     *
     * @param PaymentAttemptFactory $paymentAttemptFactory
     * @param PaymentAttemptResource $resource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly PaymentAttemptFactory        $paymentAttemptFactory,
        private readonly PaymentAttemptResource       $resource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(int $entityId): PaymentAttemptInterface
    {
        /** @var PaymentAttempt $attempt */
        $attempt = $this->paymentAttemptFactory->create();
        $this->resource->load($attempt, $entityId);
        if (!$attempt->getId()) {
            throw new NoSuchEntityException(
                __('The ZaloPay payment attempt with id "%1" does not exist.', $entityId)
            );
        }

        return $attempt;
    }

    /**
     * @inheritDoc
     */
    public function save(PaymentAttemptInterface $attempt): PaymentAttemptInterface
    {
        try {
            /** @var PaymentAttempt $attempt */
            $this->resource->save($attempt);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save the ZaloPay payment attempt: %1', $e->getMessage()),
                $e
            );
        }

        return $attempt;
    }

    /**
     * @inheritDoc
     */
    public function getByAppTransId(string $appTransId): ?PaymentAttemptInterface
    {
        if ($appTransId === '') {
            return null;
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PaymentAttemptInterface::APP_TRANS_ID, $appTransId);
        $collection->setPageSize(1);
        /** @var PaymentAttempt|null $attempt */
        $attempt = $collection->getFirstItem();
        if (!$attempt || !$attempt->getId()) {
            return null;
        }

        return $attempt;
    }

    /**
     * @inheritDoc
     */
    public function getActiveByQuoteId(int $quoteId): ?PaymentAttemptInterface
    {
        if ($quoteId <= 0) {
            return null;
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PaymentAttemptInterface::QUOTE_ID, $quoteId);
        $collection->addFieldToFilter(
            PaymentAttemptInterface::PAYMENT_STATUS,
            ['in' => [PaymentAttemptInterface::STATUS_INITIATED, PaymentAttemptInterface::STATUS_ACTIVE]]
        );
        $collection->setOrder(PaymentAttemptInterface::CREATED_AT, 'DESC');
        $collection->setPageSize(1);
        /** @var PaymentAttempt|null $attempt */
        $attempt = $collection->getFirstItem();
        if (!$attempt || !$attempt->getId()) {
            return null;
        }

        return $attempt;
    }

    /**
     * @inheritDoc
     */
    public function getListByQuoteId(int $quoteId): array    {
        if ($quoteId <= 0) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PaymentAttemptInterface::QUOTE_ID, $quoteId);
        $collection->setOrder(PaymentAttemptInterface::CREATED_AT, 'ASC');

        return array_values($collection->getItems());
    }

    /**
     * @inheritDoc
     */
    public function getBlockingAttemptByQuoteId(int $quoteId): ?PaymentAttemptInterface
    {
        if ($quoteId <= 0) {
            return null;
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PaymentAttemptInterface::QUOTE_ID, $quoteId);
        // STRUCTURED blocking conditions ONLY (corrective round 4, Blocker
        // 2; corrective round 5: valid AbstractDb parallel-array OR syntax)
        // — no last_error parsing: an attempt blocks a new provider
        // transaction when it is money-real (PAID/FINALIZED) or quarantined
        // for reconciliation (any state, any code). Generates
        // payment_status IN ('paid','finalized') OR requires_reconciliation = 1.
        $collection->addFieldToFilter(
            [PaymentAttemptInterface::PAYMENT_STATUS, PaymentAttemptInterface::REQ_RECONCILIATION],
            [
                ['in' => [
                    PaymentAttemptInterface::STATUS_PAID,
                    PaymentAttemptInterface::STATUS_FINALIZED,
                ]],
                ['eq' => 1],
            ]
        );
        $collection->setOrder(PaymentAttemptInterface::ENTITY_ID, 'DESC');
        $collection->setPageSize(1);
        /** @var PaymentAttempt|null $attempt */
        $attempt = $collection->getFirstItem();
        if (!$attempt || !$attempt->getId()) {
            return null;
        }

        return $attempt;
    }

    /**
     * @inheritDoc
     */
    public function lockByAppTransId(string $appTransId): ?PaymentAttemptInterface
    {
        $row = $this->resource->lockRowByAppTransId($appTransId);
        if ($row === null) {
            return null;
        }
        /** @var PaymentAttempt $attempt */
        $attempt = $this->paymentAttemptFactory->create();
        $attempt->setData($row);

        return $attempt;
    }

    /**
     * @inheritDoc
     */
    public function claimEmailDispatch(int $entityId, int $token, int $graceSeconds): bool
    {
        return $this->resource->claimEmailDispatch($entityId, $token, $graceSeconds);
    }

    /**
     * @inheritDoc
     */
    public function releaseEmailDispatch(int $entityId, int $token): void
    {
        $this->resource->releaseEmailDispatch($entityId, $token);
    }
}
