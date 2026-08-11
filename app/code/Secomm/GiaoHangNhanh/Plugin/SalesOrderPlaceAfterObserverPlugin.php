<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\GiaoHangNhanh\Plugin;

use Secomm\GiaoHangNhanh\Observer\SalesOrderPlaceAfterObserver;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ShipmentFactory;

class SalesOrderPlaceAfterObserverPlugin
{
    const GHN_SERVICE = [
        'giaohangnhanh_standard_giaohangnhanh_standard' =>'giaohangnhanh_standard',
        'giaohangnhanh_express_giaohangnhanh_express' =>'giaohangnhanh_express'
    ];

    /**
     * @var ShipmentFactory
     */
    protected ShipmentFactory $shipmentFactory;

    /**
     * @var ShipmentRepositoryInterface
     */
    protected ShipmentRepositoryInterface $shipmentRepository;

    /**
     * @param ShipmentFactory $shipmentFactory
     * @param ShipmentRepositoryInterface $shipmentRepository
     */
    public function __construct(
        ShipmentFactory             $shipmentFactory,
        ShipmentRepositoryInterface $shipmentRepository
    ) {
        $this->shipmentFactory = $shipmentFactory;
        $this->shipmentRepository = $shipmentRepository;
    }

    /**
     * This function will be executed after the execute function of the SalesOrderPlaceAfterObserver class
     * That will create shipment order
     *
     * @param SalesOrderPlaceAfterObserver $subject
     * @param mixed $result
     * @param Observer $observer
     * @return mixed
     * @throws LocalizedException
     */
    public function afterExecute(SalesOrderPlaceAfterObserver $subject, $result, Observer $observer)
    {
        // Retrieve order from the subject
        $order = $observer->getEvent()->getOrder();
        $trackingCode = $order->getTrackingCode();
        if (!array_key_exists($order->getShippingMethod(), self::GHN_SERVICE) || !$trackingCode) {
            return $result;
        }
        try {
            if ($order->canShip()) {
                $track = [
                    [
                        'carrier_code' => self::GHN_SERVICE[$order->getShippingMethod()],
                        'number' => (string)$trackingCode,
                        'title' => (string)$order->getShippingDescription(),
                        'description' => (string)$order->getShippingDescription()
                    ]
                ];

                $shipment = $this->shipmentFactory->create(
                    $order,
                    $this->prepareShipmentItems($order),
                    $track
                );

                $shipment->register();
                $this->shipmentRepository->save($shipment);
            }
        } catch (LocalizedException $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        return $result;
    }

    /**
     * @param $order Order
     * @return array
     * @throws \Exception
     */
    protected function prepareShipmentItems(Order $order): array
    {
        $items = [];
        foreach ($order->getAllItems() as $item) {
            if (!$item->getItemId()) {
                $order->save();
            }
            $items[$item->getItemId()] = $item->getQtyOrdered();
        }
        return $items;
    }
}
