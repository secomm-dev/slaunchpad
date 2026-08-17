<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Observer\Sales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentRepository;
use Secomm\Ahamove\Helper\Data as AhamoveHelper;
use Secomm\Ahamove\Logger\Logger;
use Secomm\Ahamove\Model\Carrier\ShippingMethod\Express;
use Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard;
use Secomm\Ahamove\Model\PackageFactory;

class OrderShipmentSaveAfter implements ObserverInterface
{
    public function __construct(
        protected AhamoveHelper           $ahamoveHelper,
        protected PackageFactory          $packageFactory,
        protected \Secomm\Ahamove\Command\CreateShipment $createShipmentCommand,
        protected TrackFactory            $trackFactory,
        protected ShipmentRepository       $shipmentRepository,
        protected MessageManagerInterface $messageManager,
        protected Logger                  $logger
    ) {
    }

    /**
     * Auto push shipment to Ahamove on sales_order_shipment_save_after
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $shipment = $observer->getEvent()->getShipment();
            if (!$shipment) {
                return;
            }

            $order = $shipment->getOrder();
            if (!$order) {
                return;
            }

            $shippingMethod = (string)$order->getShippingMethod();
            $allowedCarriers = [
                Standard::AHAMOVE_STANDARD_CARRIER_CODE,
                Express::AHAMOVE_EXPRESS_CARRIER_CODE
            ];

            // Check if order uses Ahamove shipping carrier
            $isAhamove = false;
            foreach ($allowedCarriers as $carrier) {
                if (str_contains($shippingMethod, $carrier) || str_starts_with($shippingMethod, 'ahamove')) {
                    $isAhamove = true;
                    break;
                }
            }

            if (!$isAhamove) {
                return;
            }

            // Check if auto push is enabled in config
            $storeId = $order->getStoreId();
            $autoPush = (bool)$this->ahamoveHelper->getConfig('ahamove/general/auto_push_shipment', $storeId);
            if (!$autoPush) {
                return;
            }

            // Prevent duplicate push if tracking already exists
            foreach ($shipment->getAllTracks() as $track) {
                if (str_contains((string)$track->getCarrierCode(), 'ahamove') || !empty($track->getTrackNumber())) {
                    return;
                }
            }

            // Resolve full Ahamove Service ID via Constants (e.g. SGN-BIKE, SGN-VAN-500, SGN-2H-PUBLIC)
            $serviceId = $this->ahamoveHelper->resolveServiceId($shippingMethod, $storeId);

            $package = $this->packageFactory->create();
            $package->setOrderId($order->getEntityId());
            $package->setService($serviceId);

            $result = $this->createShipmentCommand->execute($package);

            if (is_array($result) && isset($result['order']['_id'])) {
                $trackingCode = $result['order']['tracking_code'];
                $sharedLink = $result['shared_link'] ?? '';

                $track = $this->trackFactory->create();
                $track->setCarrierCode($shippingMethod);
                $track->setTitle($order->getShippingDescription() ?: 'Ahamove Delivery');
                $track->setTrackNumber($trackingCode);
                $track->setDescription($sharedLink);

                $shipment->addTrack($track);
                $this->shipmentRepository->save($shipment);

                $this->logger->info("Auto push shipment to Ahamove success. Track: $trackingCode for Order #{$order->getIncrementId()}", [], __METHOD__);
            } else {
                $this->logger->error("Auto push shipment to Ahamove failed for Order #{$order->getIncrementId()}", [], __METHOD__);
            }
        } catch (\Exception $exception) {
            $this->logger->error('OrderShipmentSaveAfter error: ' . $exception->getMessage(), [], __METHOD__);
        }
    }
}
