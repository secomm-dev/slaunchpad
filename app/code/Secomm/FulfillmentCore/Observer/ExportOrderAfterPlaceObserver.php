<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Secomm\FulfillmentCore\Model\Export\ExportOrchestrator;

/**
 * Triggers outbound OMS export after quote submit success (order already saved).
 */
class ExportOrderAfterPlaceObserver implements ObserverInterface
{
    public function __construct(
        private readonly ExportOrchestrator $exportOrchestrator
    ) {
    }

    /**
     * @param Observer $observer Event observer with order
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->exportOrchestrator->exportOrder($order);
    }
}
