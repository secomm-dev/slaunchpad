<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\PaymentCore\Api\Data\PaymentInterface;
use Secomm\PaymentCore\Api\Data\PaymentSearchResultsInterface;
use Secomm\PaymentCore\Api\Data\PaymentSearchResultsInterfaceFactory;
use Secomm\PaymentCore\Api\PaymentRepositoryInterface;
use Secomm\PaymentCore\Model\Payment as PaymentModel;
use Secomm\PaymentCore\Model\ResourceModel\Payment as PaymentResource;
use Secomm\PaymentCore\Model\ResourceModel\Payment\CollectionFactory;

/**
 * FEAT-CSWYEJ — repository for the pending-payment lifecycle record.
 */
class PaymentRepository implements PaymentRepositoryInterface
{
    /**
     * @param PaymentResource $resource
     * @param PaymentFactory $paymentFactory
     * @param ResourceModel\Payment\CollectionFactory $collectionFactory
     * @param PaymentSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly PaymentResource $resource,
        private readonly PaymentFactory $paymentFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly PaymentSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(PaymentInterface $payment): PaymentInterface
    {
        $this->resource->save($payment);
        return $payment;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $entityId): PaymentInterface
    {
        $payment = $this->paymentFactory->create();
        $this->resource->load($payment, $entityId);
        if (!$payment->getId()) {
            throw new NoSuchEntityException(__('Payment Core record not found: %1', $entityId));
        }
        return $payment;
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(int $orderId): PaymentInterface
    {
        $payment = $this->paymentFactory->create();
        $this->resource->load($payment, $orderId, PaymentInterface::ORDER_ID);
        if (!$payment->getId()) {
            throw new NoSuchEntityException(__('Payment Core record not found for order: %1', $orderId));
        }
        return $payment;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): PaymentSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(PaymentInterface $payment): bool
    {
        $this->resource->delete($payment);
        return true;
    }
}
