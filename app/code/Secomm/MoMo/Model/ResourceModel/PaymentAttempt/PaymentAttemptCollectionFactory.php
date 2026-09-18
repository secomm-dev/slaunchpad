<?php
/**
 * Non-generated factory for MoMo payment attempt collections.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model\ResourceModel\PaymentAttempt;

use Magento\Framework\ObjectManagerInterface;

class PaymentAttemptCollectionFactory
{
    /**
     * PaymentAttemptCollectionFactory constructor.
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * Create a fresh collection instance.
     *
     * @return PaymentAttemptCollection
     */
    public function create(): PaymentAttemptCollection
    {
        return $this->objectManager->create(PaymentAttemptCollection::class);
    }
}
