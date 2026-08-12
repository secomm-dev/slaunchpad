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
     * @var Config
     */
    private $config;

    /**
     * @param LoggerInterface $logger
     * @param PublisherInterface $publisher
     * @param Config $config
     */
    public function __construct(
        LoggerInterface $logger,
        PublisherInterface $publisher,
        Config $config
    ) {
        $this->logger = $logger;
        $this->publisher = $publisher;
        $this->config = $config;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        if (false === strpos($order->getShippingMethod(), Config::GHN_CODE)) {
            return;
        }
        if (!$this->config->isAutoSyncOnPlaceOrder()) {
            return;
        }

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
