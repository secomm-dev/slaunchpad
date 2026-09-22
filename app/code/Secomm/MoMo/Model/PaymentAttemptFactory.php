<?php
/**
 * Factory for MoMo payment attempt models.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Non-generated factory (ObjectManager-backed, the Magento core pattern
 * for factories of models that are not data-object generated).
 */
class PaymentAttemptFactory
{
    /**
     * PaymentAttemptFactory constructor.
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * Create a new (unsaved) payment attempt instance.
     *
     * @param array $data
     * @return PaymentAttempt
     */
    public function create(array $data = []): PaymentAttempt
    {
        return $this->objectManager->create(PaymentAttempt::class, $data);
    }
}
