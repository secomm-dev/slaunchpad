<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Shipping\Model\ShipmentNotifier;
use Secomm\Ahamove\Command\CreateShipment;
use Secomm\Ahamove\Helper\Data as AhamoveHelper;
use Secomm\Ahamove\Logger\Logger;
use Secomm\Ahamove\Model\Carrier\ShippingMethod\Express;
use Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard;
use Secomm\Ahamove\Model\PackageFactory;

class PushAhamove extends Action
{
    const ADMIN_RESOURCE = 'Magento_Sales::actions_edit';

    public function __construct(
        Context                    $context,
        protected OrderRepository    $orderRepository,
        protected ShipmentRepository $shipmentRepository,
        protected ShipmentFactory    $shipmentFactory,
        protected ShipmentNotifier   $shipmentNotifier,
        protected PackageFactory     $packageFactory,
        protected CreateShipment     $createShipmentCommand,
        protected TrackFactory       $trackFactory,
        protected AhamoveHelper      $ahamoveHelper,
        protected Logger             $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Manual Push Order to Ahamove from Order View page
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $orderId = $this->getRequest()->getParam('order_id');

        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Missing order ID.'));
            return $resultRedirect->setPath('sales/order/');
        }

        try {
            $order = $this->orderRepository->get($orderId);
            if (!$order) {
                $this->messageManager->addErrorMessage(__('Order not found.'));
                return $resultRedirect->setPath('sales/order/');
            }

            if ($order->isCanceled()
                || $order->getState() === \Magento\Sales\Model\Order::STATE_HOLDED
                || $order->getState() === \Magento\Sales\Model\Order::STATE_CLOSED
            ) {
                $this->messageManager->addErrorMessage(__('Cannot push canceled, on hold, or closed order to Ahamove.'));
                return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
            }

            if (!$order->canShip() && !$order->hasShipments()) {
                $this->messageManager->addErrorMessage(__('This order has no shipments and cannot be shipped.'));
                return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
            }

            $shippingMethod = (string)$order->getShippingMethod();
            $storeId = $order->getStoreId();
            $serviceId = $this->ahamoveHelper->resolveServiceId($shippingMethod, $storeId);

            $package = $this->packageFactory->create();
            $package->setOrderId($order->getEntityId());
            $package->setService($serviceId);

            $result = $this->createShipmentCommand->execute($package);

            if (is_array($result) && isset($result['order']['tracking_code'])) {
                $trackingCode = $result['order']['tracking_code'];
                $sharedLink = $result['shared_link'] ?? $result['order']['shared_link'] ?? '';

                // Normalize carrier code to base carrier code (e.g. ahamove_standard or ahamove_express)
                $carrierCode = str_contains($shippingMethod, Express::AHAMOVE_EXPRESS_CARRIER_CODE)
                    ? Express::AHAMOVE_EXPRESS_CARRIER_CODE
                    : Standard::AHAMOVE_STANDARD_CARRIER_CODE;

                // If order already has shipment(s), add track to existing shipment
                $shipments = $order->getShipmentsCollection();
                if ($shipments && $shipments->getSize() > 0) {
                    foreach ($shipments as $shipment) {
                        $hasTracking = false;
                        foreach ($shipment->getAllTracks() as $existingTrack) {
                            if ($existingTrack->getTrackNumber() === $trackingCode) {
                                $hasTracking = true;
                                break;
                            }
                        }
                        if (!$hasTracking) {
                            $track = $this->trackFactory->create();
                            $track->setCarrierCode($carrierCode);
                            $track->setTitle($order->getShippingDescription() ?: 'Ahamove Delivery');
                            $track->setTrackNumber($trackingCode);
                            $track->setDescription($sharedLink);
                            $shipment->addTrack($track);
                            $this->shipmentRepository->save($shipment);
                        }
                    }
                } else {
                    // Automatically create Magento Shipment if order can ship
                    if ($order->canShip()) {
                        $items = [];
                        foreach ($order->getAllItems() as $item) {
                            if ($item->getIsVirtual() || $item->getQtyToShip() <= 0) {
                                continue;
                            }
                            $items[$item->getItemId()] = $item->getQtyToShip();
                        }

                        $tracks = [
                            [
                                'carrier_code' => $carrierCode,
                                'number' => $trackingCode,
                                'title' => $order->getShippingDescription() ?: 'Ahamove Delivery',
                                'description' => $sharedLink,
                            ]
                        ];
                        $shipment = $this->shipmentFactory->create($order, $items, $tracks);
                        $shipment->register();
                        $this->shipmentRepository->save($shipment);
                        $this->orderRepository->save($order);
                        $this->shipmentNotifier->notify($shipment);
                    }
                }

                $this->messageManager->addSuccessMessage(
                    __('Pushed order #%1 to Ahamove successfully! Tracking Code: %2', $order->getIncrementId(), $trackingCode)
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('Failed to push order to Ahamove. Please check Ahamove credentials & log.')
                );
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            if ($e->getPrevious()) {
                $errorMsg .= ' (' . $e->getPrevious()->getMessage() . ')';
            }
            $this->logger->error('PushAhamove Order Controller Error: ' . $errorMsg, [], __METHOD__);
            $this->messageManager->addErrorMessage(__('Error pushing to Ahamove: %1', $errorMsg));
        }

        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
