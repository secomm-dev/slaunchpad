<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Command;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Sales\Model\OrderRepository;
use Magento\Shipping\Model\ShipmentNotifier;
use Secomm\Ahamove\Helper\Data as AhamoveHelperData;
use Secomm\Ahamove\Logger\Logger;
use Secomm\Ahamove\Model\Config;
use Secomm\Ahamove\Model\Connect\Api;
use Secomm\Ahamove\Model\Data\AhamoveAddressFactory;

class CreateShipment
{
    /**
     * The OrderRepository is used to load, save and delete orders.
     *
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * The ShipmentFactory is used to create a new Shipment.
     *
     * @var Order\ShipmentFactory
     */
    protected $shipmentFactory;

    /**
     * The ShipmentRepository is used to load, save and delete shipments.
     *
     * @var Order\ShipmentRepository
     */
    protected $shipmentRepository;

    /**
     * The ShipmentNotifier class is used to send a notification email to the customer.
     *
     * @var ShipmentNotifier
     */
    protected $shipmentNotifier;

    /**
     * @var AhamoveAddressFactory
     */
    protected $ahamoveAddressFactory;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var AhamoveHelperData
     */
    protected $ahamoveHelperData;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(
        OrderRepository       $orderRepository,
        ShipmentRepository    $shipmentRepository,
        ShipmentFactory       $shipmentFactory,
        ShipmentNotifier      $shipmentNotifier,
        AhamoveAddressFactory $ahamoveAddressFactory,
        Api                   $api,
        AhamoveHelperData     $ahamoveHelperData,
        Logger                $logger,
    ) {
        $this->orderRepository = $orderRepository;
        $this->shipmentFactory = $shipmentFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->shipmentNotifier = $shipmentNotifier;
        $this->ahamoveAddressFactory = $ahamoveAddressFactory;
        $this->api = $api;
        $this->ahamoveHelperData = $ahamoveHelperData;
        $this->logger = $logger;
    }

    /**
     * Observer for Create Order by API and Create Shipment
     *
     * @param Observer $observer
     *
     * @return void
     * @throws Exception
     */
    public function execute($package)
    {
        try {
            $order = $this->orderRepository->get($package->getOrderId());
            $params = $this->prepareShippingData($order, $package->getService());

            $this->logger->info("Creating Ahamove order for Order #{$order->getIncrementId()} with params: " . json_encode($params, JSON_UNESCAPED_UNICODE), [], __METHOD__);

            $response = $this->api->name('Create Order Ahamove')
                ->withContentType('application/json')
                ->withHeader('Authorization: Bearer ' . $this->ahamoveHelperData->getToken($order->getStoreId()))
                ->to(Config::URL_AHAMOVE_CREATE_ORDER)
                ->withData($params)
                ->asJsonResponse(true)
                ->post();

            if (isset($response->status) && $response->status == Config\Source\ApiRequest\Status::STATUS_CODE_SUCCESS) {
                $response->content['order']['status_label'] = $this->ahamoveHelperData->getStatusLabel($response->content['order']['status']);
                $this->logger->info("Ahamove order created successfully: " . json_encode($response->content, JSON_UNESCAPED_UNICODE), [], __METHOD__);
                return $response->content;
            } else {
                $this->logger->error("Ahamove order creation failed with status: " . json_encode($response, JSON_UNESCAPED_UNICODE), [], __METHOD__);
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Creates a new shipment for the specified order.
     *
     * @param Order $order
     * @throws Exception
     */
    protected function createShipment($order, $dataResponse)
    {
        try {
            // check if it's possible to ship the items
            if ($order->canShip()) {
                $status = $dataResponse['status'];

                $track = [
                    [
                        'carrier_code' => $order->getShippingMethod(),
                        'number' => $dataResponse['order_id'],
                        'title' => $order->getShippingDescription(),
                        'description' => 'des status' . $dataResponse['shared_link'],
                    ]
                ];

                // create the shipment
                $shipment = $this->shipmentFactory->create(
                    $order,
                    $this->prepareShipmentItems($order),
                    $track
                );
                $shipment->register();

                // save the newly created shipment
                $this->shipmentRepository->save($shipment);

                // send shipping confirmation e-mail to customer
                $this->shipmentNotifier->notify($shipment);
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param $order Order
     * @return array
     */
    protected function prepareShipmentItems($order)
    {
        $items = [];
        foreach ($order->getAllItems() as $item) {
            $items[$item->getItemId()] = $item->getQtyOrdered();
        }
        return $items;
    }

    protected function prepareShippingData($order, $serviceId)
    {
        $shippingAddress = $order->getShippingAddress();
        $ahamoveAddressFactory = $this->ahamoveAddressFactory->create();
        $items = [];
        $ahamoveAddressFactory->setCityFrom($this->ahamoveHelperData->getCity())
            ->setRegionCodeFrom((string)$this->ahamoveHelperData->getShippingRegion())
            ->setStreetFrom((string)$this->ahamoveHelperData->getShippingStreet())
            ->setPostCodeFrom((string)$this->ahamoveHelperData->getShippingPostcode())
            ->setNameFrom('')
            ->setPhoneFrom('');

        $ahamoveAddressFactory->setCityTo($shippingAddress->getCity())
            ->setRegionCodeTo((string)$shippingAddress->getRegion())
            ->setStreetTo((string)$this->getStreetFull($shippingAddress->getStreet()))
            ->setPostCodeTo((string)$shippingAddress->getPostcode())
            ->setNameTo((string)$shippingAddress->getFirstName() . ' ' . (string)$shippingAddress->getLastName())
            ->setRemark('')
            ->setPhoneTo('');

        $data = [
            [
                'address' => $ahamoveAddressFactory->buildAddress('from'),
                'short_address' => '',
                'name' => '',
                'mobile' => (string)$this->ahamoveHelperData->getMobilePhoneValue(),
                'remarks' => ''
            ],
            [
                'address' => $ahamoveAddressFactory->buildAddress('to'),
                'short_address' => '',
                'name' => (string)$shippingAddress->getFirstName() . ' ' . (string)$shippingAddress->getLastName(),
                'mobile' => (string)$shippingAddress->getTelephone(),
                'remarks' => '',
                'tracking_number' => $order->getIncrementId() . '_' . time()
            ]
        ];

        // Items
        foreach ($order->getAllItems() as $item) {
            $items[] = [
                '_id'=> $item->getSku(),
                'num'=> (int)$item->getQtyOrdered(),
                'name'=> $item->getName(),
                'price'=> $this->ahamoveHelperData->convertPriceToDefaultCurrency($item->getPrice())
            ];
        }

        return [
            'service_id' => $serviceId,
            'payment_method' => $this->ahamoveHelperData->getPaymentMethod(),
            'promo_code' => '',
            'remarks' => '',
            'order_time' => 0,
            'requests' => [],
            'path' => $data,
            'items' => $items,
        ];
    }

    /**
     * @return mixed
     */
    private function getServiceId()
    {
        return $this->ahamoveHelperData->getAhamoveService();
    }

    /**
     * @param $address
     *
     * @return mixed|string
     */
    private function getStreetFull($address)
    {
        return is_array($address) ? implode("\n", $address) : $address;
    }
}
