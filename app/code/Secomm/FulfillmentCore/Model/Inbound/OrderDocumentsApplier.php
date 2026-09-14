<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Inbound;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Convert\Order as OrderConverter;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Secomm\FulfillmentCore\Api\Data\InboundUpdate;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Throwable;

/**
 * Create Magento invoice / shipment when inbound Status Mapping changes order status.
 * Uses Magento Sales APIs only (no Secomm shipping carrier modules).
 */
class OrderDocumentsApplier
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TransactionFactory $transactionFactory,
        private readonly OrderConverter $orderConverter,
        private readonly TrackFactory $trackFactory,
        private readonly FulfillmentLogger $fulfillmentLogger
    ) {
    }

    /**
     * Ensure invoice and/or shipment exist for the target Magento status.
     *
     * @param Order $order Magento sales order (fresh-loaded)
     * @param string $magentoStatus Target sales_order.status from Status Mapping
     * @param string|null $magentoState Resolved Magento state for that status
     * @param InboundUpdate $update Carrier/tracking from POS
     * @return Order Reloaded-safe order instance after document registration
     */
    public function apply(
        Order $order,
        string $magentoStatus,
        ?string $magentoState,
        InboundUpdate $update
    ): Order {
        $service = $update->getServiceCode();
        if ($this->isCancelledTarget($magentoStatus, $magentoState)) {
            $this->fulfillmentLogger->info(
                $service,
                'FulfillmentCore invoice/shipment skipped (cancelled target)',
                ['order_id' => (int) $order->getEntityId(), 'magento_status' => $magentoStatus]
            );
            return $order;
        }

        // When Status Mapping changes Magento status: create missing invoice then shipment
        // so complete/shipped flows can progress (Magento requires documents for complete).
        $order = $this->createInvoiceIfPossible($order, $service);
        $order = $this->createShipmentIfPossible($order, $update);

        return $order;
    }

    /**
     * @param string $magentoStatus Target status code
     * @param string|null $magentoState Target state code
     */
    private function isCancelledTarget(string $magentoStatus, ?string $magentoState): bool
    {
        $state = $magentoState ?? '';
        return $state === Order::STATE_CANCELED
            || in_array($magentoStatus, [Order::STATE_CANCELED, 'canceled', 'cancelled'], true);
    }

    /**
     * @param Order $order Magento order
     * @param string $serviceCode Adapter service_code for logs
     */
    private function createInvoiceIfPossible(Order $order, string $serviceCode): Order
    {
        try {
            if (!$order->canInvoice()) {
                $this->fulfillmentLogger->info(
                    $serviceCode,
                    'FulfillmentCore invoice skipped (canInvoice=false)',
                    ['order_id' => (int) $order->getEntityId()]
                );
                return $order;
            }

            $invoice = $this->invoiceService->prepareInvoice($order);
            if (!$invoice || !(float) $invoice->getTotalQty()) {
                $this->fulfillmentLogger->info(
                    $serviceCode,
                    'FulfillmentCore invoice skipped (empty qty)',
                    ['order_id' => (int) $order->getEntityId()]
                );
                return $order;
            }

            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);

            $transaction = $this->transactionFactory->create();
            $transaction->addObject($invoice);
            $transaction->addObject($invoice->getOrder());
            $transaction->save();

            $this->fulfillmentLogger->info(
                $serviceCode,
                'FulfillmentCore invoice created',
                [
                    'order_id' => (int) $order->getEntityId(),
                    'invoice_id' => (int) $invoice->getEntityId(),
                ]
            );

            return $invoice->getOrder();
        } catch (Throwable $e) {
            $this->fulfillmentLogger->error(
                $serviceCode,
                'FulfillmentCore invoice failed',
                [
                    'order_id' => (int) $order->getEntityId(),
                    'error' => $e->getMessage(),
                ]
            );
            return $order;
        }
    }

    /**
     * @param Order $order Magento order
     * @param InboundUpdate $update Tracking fields from POS
     */
    private function createShipmentIfPossible(Order $order, InboundUpdate $update): Order
    {
        $service = $update->getServiceCode();
        try {
            if (!$order->canShip()) {
                $this->fulfillmentLogger->info(
                    $service,
                    'FulfillmentCore shipment skipped (canShip=false)',
                    ['order_id' => (int) $order->getEntityId()]
                );
                return $order;
            }

            $shipment = $this->orderConverter->toShipment($order);
            $hasQty = false;
            foreach ($order->getAllItems() as $orderItem) {
                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }
                $qty = (float) $orderItem->getQtyToShip();
                if ($qty <= 0) {
                    continue;
                }
                $shipmentItem = $this->orderConverter->itemToShipmentItem($orderItem);
                $shipmentItem->setQty($qty);
                $shipment->addItem($shipmentItem);
                $hasQty = true;
            }

            if (!$hasQty) {
                $this->fulfillmentLogger->info(
                    $service,
                    'FulfillmentCore shipment skipped (no shippable qty)',
                    ['order_id' => (int) $order->getEntityId()]
                );
                return $order;
            }

            $shipment->register();
            $shipment->getOrder()->setIsInProcess(true);

            $trackNumber = $update->getTrackingNumber();
            if ($trackNumber !== null && $trackNumber !== '') {
                $track = $this->trackFactory->create();
                $track->setNumber($trackNumber);
                $track->setCarrierCode('custom');
                $track->setTitle($update->getCarrierName() ?: ucfirst($service));
                $shipment->addTrack($track);
            }

            $transaction = $this->transactionFactory->create();
            $transaction->addObject($shipment);
            $transaction->addObject($shipment->getOrder());
            $transaction->save();

            $this->fulfillmentLogger->info(
                $service,
                'FulfillmentCore shipment created',
                [
                    'order_id' => (int) $order->getEntityId(),
                    'shipment_id' => (int) $shipment->getEntityId(),
                    'tracking' => $trackNumber,
                ]
            );

            return $shipment->getOrder();
        } catch (Throwable $e) {
            $this->fulfillmentLogger->error(
                $service,
                'FulfillmentCore shipment failed',
                [
                    'order_id' => (int) $order->getEntityId(),
                    'error' => $e->getMessage(),
                ]
            );
            return $order;
        }
    }
}
