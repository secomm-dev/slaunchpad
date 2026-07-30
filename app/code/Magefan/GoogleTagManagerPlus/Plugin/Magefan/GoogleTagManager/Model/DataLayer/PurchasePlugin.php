<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\GoogleTagManagerPlus\Plugin\Magefan\GoogleTagManager\Model\DataLayer;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magefan\GoogleTagManager\Model\DataLayer\Purchase as Subject;
use Magento\Sales\Model\Order;

class PurchasePlugin
{
    /**
     * 540 days interval
     */
    const PERIOD = 'P540D';

    /**
     * 5 minutes interval
     */
    const EXCLUDE_RECENT_PERIOD = 'PT5M';

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * @param Subject $subject
     * @param $result
     * @param Order $order
     * @return mixed
     */
    public function afterGet(
        Subject $subject,
        $result,
        Order $order
    ) {
        if (!$result || !$order) {
            return $result;
        }

        $date = new \DateTime();
        $interval = new \DateInterval(self::PERIOD);
        $date->sub($interval);
        $formattedDate = $date->format('Y-m-d H:i:s');

        $orders = $this->orderCollectionFactory->create()
            ->addFieldToFilter('created_at', ['gteq' => $formattedDate]);

        if ($entityId = (int)$order->getId()) {
            $orders->addFieldToFilter('entity_id', ['neq' => $entityId]);
        } else {
            $recentDate = new \DateTime('now', new \DateTimeZone('UTC'));
            $recentInterval = new \DateInterval(self::EXCLUDE_RECENT_PERIOD);
            $recentDate->sub($recentInterval);
            $formattedRecentDate = $recentDate->format('Y-m-d H:i:s');
            $orders->addFieldToFilter('created_at', ['lteq' => $formattedRecentDate]);
        }

        if ($customerId = $order->getCustomerId()) {
            $orders->addFieldToFilter(
                ['customer_id', 'customer_email'],
                [
                    ['eq' => $customerId],
                    ['eq' => $order->getCustomerEmail()]
                ]
            );
        } else {
            $orders->addFieldToFilter('customer_email', $order->getCustomerEmail());
        }

        $result['new_customer'] = !count($orders);

        return $result;
    }
}
