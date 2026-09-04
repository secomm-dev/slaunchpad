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
use Secomm\AddressDropdown\Helper\Address;

class Shipping
{
    const CUSTOM_CITY = 'custom_city';
    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    protected DataObject $dataObject;

    /**
     * @var Address
     */
    private Address $addressHelper;

    public function __construct(
        RequestInterface           $request,
        DataObject                 $dataObject,
        Address                    $addressHelper
    )
    {
        $this->request = $request;
        $this->dataObject = $dataObject;
        $this->addressHelper = $addressHelper;
    }

    /**
     * TASK-6MKF0V: sub_city retired (DEC-TASK6MKF0V-001) — this plugin keeps only the
     * custom_city → dest_city mapping for cart estimation plus the dest_city display-name
     * translation. City levels come from the Address Profile engine (city depth), so no
     * third-level dest data is set on the rate request anymore.
     *
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

                $destCity = $rateRequest->getDestCity();
                $customAttributes = $addressInformation->getAddress('custom_attributes');
                if (isset($customAttributes) && count($customAttributes)) {
                    foreach ($customAttributes as $customAttribute) {
                        // Estimate in cart
                        if ($customAttribute['attribute_code'] === self::CUSTOM_CITY && !empty($customAttribute['value'])) {
                            $rateRequest->setData('dest_city', $customAttribute['value']);
                        }
                    }
                }
                if ($destCity) {
                    $destCity = $this->addressHelper->getCityNameByDefaultName($destCity, $rateRequest->getDestRegionId());
                    $rateRequest->setData('dest_city', $destCity);
                }
            }
        } catch (Exception $exception) {
            /* The untranslated rate request values are left as-is. */
        }
    }
}