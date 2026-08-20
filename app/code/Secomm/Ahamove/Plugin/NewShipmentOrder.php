<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Plugin;

use Secomm\Ahamove\Controller\Webhooks\Index;

class NewShipmentOrder
{
    public function __construct(
        protected \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
    }

    /**
     * @param Index $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterExecute(Index $subject, mixed $result): mixed
    {
        if ($result instanceof \Secomm\Ahamove\Model\AhamoveOrderStatus && $result->getStatus() === 'COMPLETED') {
            $this->eventManager->dispatch(
                'evt_packaging_manager_auto_create_shipment',
                ['package' => $result]
            );
        }

        return $result;
    }
}
