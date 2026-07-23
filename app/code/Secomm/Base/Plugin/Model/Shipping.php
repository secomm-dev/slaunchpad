<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Base\Plugin\Model;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Model\Session;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Shipping as MageShipping;

class Shipping
{
    protected ProductRepository $productRepository;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    public function __construct(
        ProductRepository $productRepository,
        RequestInterface  $request,
        protected AddressRepositoryInterface $addressRepository,
        protected Session $checkoutSession,
        protected DataObject $dataObject,
    ) {
        $this->productRepository = $productRepository;
        $this->request = $request;
    }

    /**
     * @param MageShipping $subject
     * @param RateRequest $rateRequest
     */
    public function beforeCollectRates(MageShipping $subject, RateRequest $rateRequest): void
    {
        try {
            $addressInformation = $this->request->getContent();
            /**
             * This is for the case when estimate shipping is called from the SHOPPING CART page
             */
            if ($addressInformation !== "") {
                $addressInformation = json_decode($addressInformation, 1);
                if (is_null($addressInformation)) {
                    return;
                }
                //Convert array to Data Object
                $addressInformation = $this->dataObject->addData($addressInformation);

                //Customer is logged in
                if (!is_null($addressInformation->getData('addressId'))) {
                    $addressData = $this->addressRepository->getById($addressInformation->getData('addressId'));
                    if (!is_null($addressData->getRegion())) {
                        $rateRequest->setData('dest_region', $addressData->getRegion()->getRegion());
                    }
                }

                //Customer is not logged in or estimate shipping fee in cart page
                if (isset($addressInformation['addressInformation']['address'])) {
                    $shippingAddress = $this->dataObject->addData($addressInformation['addressInformation']['address']);
                    $rateRequest->setDestStreet($this->getStreetFull($shippingAddress));
                    $rateRequest->setDestStreet($this->getStreetFull($shippingAddress));
                    $rateRequest->setDestCity($shippingAddress->getCity());
                }
            }
        } catch (Exception $exception) {
        }

        $total = $this->getTotal($rateRequest->getAllItems());
        $rateRequest->setData('package_width', $total['totalWidth']);
        $rateRequest->setData('package_length', $total['totalLength']);
        $rateRequest->setData('package_height', $total['totalHeight']);
    }

    /**
     * @param array $items
     * @return float[]|int[]
     */
    public function getTotal(array $items): array
    {
        $totalWidth = 0;
        $totalLength = 0;
        $totalHeight = 0;
        $totalWeight = 0;
        foreach ($items as $item) {
            $productId = $item->getProduct()->getId();
            $quantity = !is_null($item->getQty()) ? $item->getQty() : $item->getQtyOrdered();

            try {
                /** @var Product $product */
                $product = $this->productRepository->getById($productId);
                $width = (float)$product->getData('width');
                $length = (float)$product->getData('length');
                $height = (float)$product->getData('height');
                $weight = (float)$product->getData('weight');
                $totalWidth += $width * $quantity;
                $totalLength += $length * $quantity;
                $totalHeight += $height * $quantity;
                $totalWeight += $weight * $quantity;
            } catch (NoSuchEntityException $e) {
            }
        }
        return [
            'totalWidth' => $totalWidth,
            'totalLength' => $totalLength,
            'totalHeight' => $totalHeight,
            'totalWeight' => $totalWeight
        ];
    }

    /**
     * @param $addressObject
     * @return mixed|string
     */
    private function getStreetFull($addressObject): mixed
    {
        $street = $addressObject->getData('street');
        if (is_null($street)) {
            return '';
        }
        return is_array($street) ? implode("\n", $street) : ($street ?? '');
    }
}
