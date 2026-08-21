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
 * @package     Mageplaza_Osc
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Plugin\Sales\Model;

use Magento\Sales\Api\Data\OrderExtensionInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class OrderGet
 * @package Mageplaza\ExtraFee\Plugin\Sales\Model
 */
class OrderGet
{
    /**
     * @var OrderExtensionInterfaceFactory
     */
    protected $orderExtensionInterfaceFactory;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * OrderGet constructor.
     *
     * @param OrderExtensionInterfaceFactory $orderExtensionInterfaceFactory
     * @param Data $helperData
     */
    public function __construct(
        OrderExtensionInterfaceFactory $orderExtensionInterfaceFactory,
        Data $helperData
    ) {
        $this->orderExtensionInterfaceFactory = $orderExtensionInterfaceFactory;
        $this->helperData                     = $helperData;
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $resultOrder
     *
     * @return OrderInterface
     */
    public function afterGet(
        OrderRepositoryInterface $subject,
        OrderInterface $resultOrder
    ) {
        $this->setMpExtraFee($resultOrder);

        return $resultOrder;
    }

    /**
     * @param OrderInterface $order
     */
    public function setMpExtraFee(OrderInterface $order)
    {
        $extraFee   = $this->helperData->getExtraFeeTotals($order);
        $attributes = $order->getExtensionAttributes();
        if ($attributes === null) {
            $attributes = $this->orderExtensionInterfaceFactory->create();
        }

        $attributes->setMpExtraFee($extraFee);
        $order->setExtensionAttributes($attributes);
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $searchResult
     *
     * @return OrderSearchResultInterface
     */
    public function afterGetList(OrderRepositoryInterface $subject, $searchResult)
    {
        foreach ($searchResult->getItems() as $order) {
            $this->setMpExtraFee($order);
        }

        return $searchResult;
    }
}
