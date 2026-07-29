<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   Giao hang nhanh
 */
namespace Boolfly\GiaoHangNhanh\Plugin\Shipping\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Shipping\Model\Shipping as MageShipping;

/**
 * Class Shipping
 *
 * @package Boolfly\GiaoHangNhanh\Plugin\Shipping\Model
 */
class Shipping
{
    /**
     * @param MageShipping $subject
     * @param RateRequest $rateRequest
     */
    public function beforeCollectRates(MageShipping $subject, RateRequest $rateRequest)
    {
        try {
            $shippingAddress = $this->getShippingAddress($rateRequest);
            $rateRequest->setShippingAddress($shippingAddress);
            $this->setValueAttribute($rateRequest);

            // if ($district = $shippingAddress->getDistrict()) {
            //     $rateRequest->setDistrict($district);
            // }
        } catch (LocalizedException $exception) {
            return;
        }
    }

    /**
     * Normalize rate request items. In rare cases they are not set at all.
     *
     * @param RateRequest $rateRequest
     * @return AbstractItem[]
     */
    private function getItems(RateRequest $rateRequest)
    {
        if (!$rateRequest->getAllItems()) {
            return [];
        }

        return $rateRequest->getAllItems();
    }

    /**
     * Extract shipping address from rate request.
     *
     * @param RateRequest $rateRequest
     * @return Address
     * @throws LocalizedException
     */
    private function getShippingAddress(RateRequest $rateRequest)
    {
        $itemsToShip = $this->getItems($rateRequest);
        $currentItem = current($itemsToShip);

        if ($currentItem === false) {
            throw new LocalizedException(__('No items to ship found in rates request.'));
        }

        return $currentItem->getAddress();
    }

    /**
     * Set value for attribute of product
     * @param RateRequest $rateRequest
     * @return void
     * @throws LocalizedException
     */
    public function setValueAttribute(RateRequest $rateRequest)
    {
        if (!empty($allItems = $this->getItems($rateRequest))) {
            foreach ($allItems as &$item) {
                $product = $item->getProduct();
                $item->setData('height', $product->getHeight() ?? 0);
                $item->setData('width', $product->getWidth() ?? 0);
                $item->setData('length', $product->getLength() ?? 0);
            }
            $rateRequest->setAllItems($allItems);
        }
    }
}
