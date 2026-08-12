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
 * Publish sync message when a shipment is created for a GHN order that has not yet been synced.
 * Guard: only fires when order has no tracking_code (not already synced) to prevent
 * feedback loop — OrderSyncConsumer creates shipment AFTER setting tracking_code.
 */
class SalesShipmentSaveAfterObserver implements ObserverInterface
{
    /**
     * @param LoggerInterface $logger
     * @param PublisherInterface $publisher
     * @param Config $config
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PublisherInterface $publisher,
        private readonly Config $config
    ) {}

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment?->getOrder();
        if (!$order) {
            return;
        }

        // Only GHN shipping orders
        if (false === strpos((string) $order->getShippingMethod(), Config::GHN_CODE)) {
            return;
        }

        // GUARD: already synced → do NOT re-publish (prevents duplicate GHN order)
        if ($order->getTrackingCode()) {
            return;
        }

        if (!$this->config->isAutoSyncOnShipmentCreate()) {
            return;
        }

        try {
            $this->publisher->publish(
                'ghn.sync.order',
                json_encode(['order_id' => $order->getId()])
            );
        } catch (\Exception $e) {
            $this->logger->error('[GHN] Failed to dispatch sync message on shipment save: ' . $e->getMessage());
        }
    }
}
