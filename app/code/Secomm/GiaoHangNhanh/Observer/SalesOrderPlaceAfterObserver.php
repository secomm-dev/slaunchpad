<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Observer;

use Secomm\GiaoHangNhanh\Model\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Psr\Log\LoggerInterface;

/**
 * Class SalesOrderPlaceAfterObserver
 *
 * @package Secomm\GiaoHangNhanh\Observer
 */
class SalesOrderPlaceAfterObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @param LoggerInterface $logger
     * @param PublisherInterface $publisher
     */
    public function __construct(
        LoggerInterface $logger,
        PublisherInterface $publisher
    ) {
        $this->logger = $logger;
        $this->publisher = $publisher;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        if (false !== strpos($order->getShippingMethod(), Config::GHN_CODE)) {
            try {
                $this->publisher->publish(
                    'ghn.sync.order',
                    json_encode(['order_id' => $order->getId()])
                );
            } catch (\Exception $e) {
                $this->logger->error('[GHN] Failed to dispatch sync message: ' . $e->getMessage());
            }
        }
    }
}
