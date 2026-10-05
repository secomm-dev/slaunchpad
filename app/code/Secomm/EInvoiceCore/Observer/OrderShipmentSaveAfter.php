<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceCore\Model\Config;
use Secomm\EInvoiceCore\Model\Schedule\FirstShipmentGate;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;

/**
 * Schedules electronic invoice issuance after the first shipment is saved.
 */
class OrderShipmentSaveAfter extends AbstractOrderEventObserver
{
    /**
     * @param IssueInvoiceService $issueInvoiceService
     * @param Config $config
     * @param LoggerInterface $logger
     * @param FirstShipmentGate $firstShipmentGate
     */
    public function __construct(
        IssueInvoiceService $issueInvoiceService,
        Config $config,
        LoggerInterface $logger,
        private readonly FirstShipmentGate $firstShipmentGate
    ) {
        parent::__construct($issueInvoiceService, $config, $logger);
    }

    /**
     * @inheritdoc
     */
    protected function shouldSchedule(Order $order): bool
    {
        return $this->firstShipmentGate->isFirstShipment($order);
    }

    /**
     * @inheritdoc
     */
    protected function getTrigger(): string
    {
        return Config::TRIGGER_ON_SHIPMENT;
    }

    /**
     * @inheritdoc
     */
    protected function extractOrder(Observer $observer): ?Order
    {
        $shipment = $observer->getEvent()->getShipment();
        if (!$shipment instanceof Shipment) {
            return null;
        }

        $order = $shipment->getOrder();
        return $order instanceof Order ? $order : null;
    }
}
