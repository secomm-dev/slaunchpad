<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Secomm\PaymentCore\Model\Lifecycle\AssignManagedPayment;

/**
 * FEAT-CSWYEJ — delegate place-after snapshotting to the lifecycle service.
 * Observer stays thin (AGENTS §7.2: no logic in observers).
 */
class AssignManagedPaymentObserver implements ObserverInterface
{
    public function __construct(
        private readonly AssignManagedPayment $assignManagedPayment
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if ($order === null) {
            return;
        }
        $this->assignManagedPayment->execute($order);
    }
}
