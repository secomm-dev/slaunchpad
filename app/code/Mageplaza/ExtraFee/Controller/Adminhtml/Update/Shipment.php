<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Controller\Adminhtml\Update;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader;
use Mageplaza\ExtraFee\Block\Sales\Order\Shipment\Create\ExtraFeeUpdateQty;

/**
 * Class Shipment
 * @package Mageplaza\ExtraFee\Controller\Adminhtml\Update
 */
class Shipment extends Action
{
    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var LayoutInterface
     */
    protected $layout;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ShipmentLoader
     */
    protected $shipmentLoader;

    /**
     * Shipment constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param LayoutInterface $layout
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentLoader $shipmentLoader
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        LayoutInterface $layout,
        OrderRepositoryInterface $orderRepository,
        ShipmentLoader $shipmentLoader
    ) {
        $this->jsonFactory     = $jsonFactory;
        $this->layout          = $layout;
        $this->orderRepository = $orderRepository;
        $this->shipmentLoader  = $shipmentLoader;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->jsonFactory->create();

        try {
            $result  = [];
            $orderId = $this->getRequest()->getParam('order_id');
            $data    = $this->getRequest()->getParam('shipment');
            $order   = $this->orderRepository->get($orderId);
            $this->shipmentLoader->setOrderId($orderId);
            $this->shipmentLoader->setShipmentId($this->getRequest()->getParam('shipment_id'));
            $this->shipmentLoader->setShipment($data);
            $this->shipmentLoader->setTracking($this->getRequest()->getParam('tracking'));
            $shipment = $this->shipmentLoader->load();
            if ($shipment && $order) {
                $extraFeePayment  = $this->getExtraFeeInfo($order, $shipment)
                    ->setTemplate('Mageplaza_ExtraFee::order/shipment/create/update/extra-fee-payment.phtml')
                    ->toHtml();
                $extraFeeShipping = $this->getExtraFeeInfo($order, $shipment)
                    ->setTemplate('Mageplaza_ExtraFee::order/shipment/create/update/extra-fee-shipping.phtml')
                    ->toHtml();
                $extraFee         = $this->getExtraFeeInfo($order, $shipment)
                    ->setTemplate('Mageplaza_ExtraFee::order/shipment/create/update/extra-fee.phtml')
                    ->toHtml();

                $result = [
                    'extraFeePayment'  => $extraFeePayment,
                    'extraFeeShipping' => $extraFeeShipping,
                    'extraFee'         => $extraFee,
                ];
            }
        } catch (Exception $e) {
            $result = [];
        }

        return $resultJson->setData($result);
    }

    /**
     * @param Object $order
     * @param Object $shipment
     *
     * @return mixed
     */
    protected function getExtraFeeInfo($order, $shipment)
    {
        return $this->layout->createBlock(ExtraFeeUpdateQty::class)->setShipmentOrder($order)->setShipment($shipment);
    }
}
