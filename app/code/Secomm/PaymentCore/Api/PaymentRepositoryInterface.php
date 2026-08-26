<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\PaymentCore\Api\Data\PaymentInterface;

/**
 * FEAT-CSWYEJ — persistence contract for the pending-payment lifecycle record.
 */
interface PaymentRepositoryInterface
{
    public function save(PaymentInterface $payment): PaymentInterface;

    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): PaymentInterface;

    /**
     * Active-or-resolved record for the order, regardless of status.
     *
     * @throws NoSuchEntityException
     */
    public function getByOrderId(int $orderId): PaymentInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    public function delete(PaymentInterface $payment): bool;
}
