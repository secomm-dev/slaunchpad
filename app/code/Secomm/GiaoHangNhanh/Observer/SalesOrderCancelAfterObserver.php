<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Class SalesOrderCancelAfterObserver
 *
 * @package Secomm\GiaoHangNhanh\Observer
 */
class SalesOrderCancelAfterObserver implements ObserverInterface
{
    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param PublisherInterface $publisher
     * @param LoggerInterface $logger
     */
    public function __construct(
        PublisherInterface $publisher,
        LoggerInterface $logger
    ) {
        $this->publisher = $publisher;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        if ($order->getData('ghn_status') && $order->getData('tracking_code')) {
            try {
                $this->publisher->publish(
                    'ghn.cancel.order',
                    json_encode(['order_id' => $order->getId()])
                );
            } catch (\Exception $e) {
                $this->logger->error('[GHN] Failed to dispatch cancel message: ' . $e->getMessage());
            }
        }
    }
}
