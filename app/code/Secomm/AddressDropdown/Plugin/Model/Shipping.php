<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Plugin\Model;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Shipping as MageShipping;
use Magento\Framework\DataObject;
use Magento\Customer\Api\AddressRepositoryInterface;
use Secomm\AddressDropdown\Helper\Address;

class Shipping
{
    const SUB_CITY = 'sub_city';
    const CUSTOM_CITY = 'custom_city';
    const CUSTOM_SUB_CITY = 'custom_sub_city';
    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    protected DataObject $dataObject;

    /**
     * @var AddressRepositoryInterface
     */
    private AddressRepositoryInterface $addressRepository;

    /**
     * @var Address
     */
    private Address $addressHelper;

    public function __construct(
        RequestInterface           $request,
        DataObject                 $dataObject,
        AddressRepositoryInterface $addressRepository,
        Address                    $addressHelper
    )
    {
        $this->request = $request;
        $this->dataObject = $dataObject;
        $this->addressRepository = $addressRepository;
        $this->addressHelper = $addressHelper;
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

                if ($addressInformation === null) {
                    return;
                }
                //Convert array to Data Object
                $addressInformation = $this->dataObject->addData($addressInformation);

                $addressId = $this->getAddressIdFromRequest($addressInformation);
                $destCity = $rateRequest->getDestCity();
                if (!is_null($addressId)) {
                    $addressData = $this->addressRepository->getById($addressId);
                    $subCity = $addressData->getExtensionAttributes()->getSubCity();
                } else {
                    $subCity = null;
                    $customAttributes = $addressInformation->getAddress('custom_attributes');
                    if (isset($customAttributes) && count($customAttributes)) {
                        foreach ($customAttributes as $customAttribute) {
                            if ($customAttribute['attribute_code'] === self::SUB_CITY && !empty($customAttribute['value'])) {
                                $subCity = $this->addressHelper->getSubCityNameByDefaultName($customAttribute['value'], $destCity);
                            }
                            // Estimate in cart
                            if ($customAttribute['attribute_code'] === self::CUSTOM_CITY && !empty($customAttribute['value'])) {
                                $rateRequest->setData('dest_city', $this->addressHelper->getSubCityNameByDefaultName($customAttribute['value'], $destCity));
                            }
                            if ($customAttribute['attribute_code'] === self::CUSTOM_SUB_CITY && !empty($customAttribute['value'])) {
                                $subCity = $this->addressHelper->getSubCityNameByDefaultName($customAttribute['value'], $destCity);
                            }
                        }
                    }
                }
                if ($destCity) {
                    $destCity = $this->addressHelper->getCityNameByDefaultName($destCity, $rateRequest->getDestRegionId());
                    $rateRequest->setData('dest_city', $destCity);
                }
                if ($subCity) {
                    $rateRequest->setData('sub_city', $subCity);
                }
            }
        } catch (Exception $exception) {
            $rateRequest->setData('sub_city', null);
        }
    }

    /**
     * @param DataObject $addressInformation
     * @return mixed
     */
    private function getAddressIdFromRequest(DataObject $addressInformation): mixed
    {
        try {
            // Request from shipping fee method place
            if ($addressInformation->getData('addressId') !== null) {
                return $addressInformation->getData('addressId');
            }

            // Request from order summary place
            if ($addressInformation->getData('addressInformation') !== null
                && isset($addressInformation->getData('addressInformation')['customerAddressId'])
            ) {
                return $addressInformation->getData('addressInformation')['customerAddressId'];
            }

            //other case
            if ($addressInformation->getData('addressInformation') !== null
                && isset($addressInformation->getData('addressInformation')['shipping_address']['customerAddressId'])
            ) {
                return $addressInformation->getData('addressInformation')['shipping_address']['customerAddressId'];
            }
            return null;
        } catch (\Exception $exception) {
            return null;
        }
    }
}