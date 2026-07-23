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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Block\Adminhtml\Analysis;

use Magento\Backend\Block\Template;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Module\FullModuleList;
use Mageplaza\AbandonedCart\Helper\Data;

/**
 * Class Preview
 * @package Mageplaza\AbandonedCart\Block\Adminhtml\Template
 */
class ShoppingBehavior extends Template
{
    /**
     * @var FullModuleList
     */
    protected $moduleList;

    /**
     * @var Data
     */
    private $helperData;

    /**
     * ShoppingBehavior constructor.
     *
     * @param FullModuleList $moduleList
     * @param Data $helperData
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        FullModuleList $moduleList,
        Data $helperData,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->moduleList =$moduleList;
        $this->helperData = $helperData;
    }

    /**
     * @return array
     */
    public function addressFields()
    {
        $data = [
            AddressInterface::FIRSTNAME  => __('First Name'),
            AddressInterface::LASTNAME   => __('Last Name'),
            AddressInterface::TELEPHONE  => __('Phone Number'),
            AddressInterface::STREET     => __('Address'),
            AddressInterface::CITY       => __('City'),
            AddressInterface::REGION_ID  => __('State'),
            AddressInterface::POSTCODE   => __('Zip Code'),
            AddressInterface::COUNTRY_ID => __('Country')
        ];

        if ($this->checkModuleOsc() && $this->helperData->isEnabledOsc()) {
            for ($i = 1; $i <= 3; $i++) {
                $key = 'mposc_field_' . $i;
                $data[$key] = $this->helperData->getCustomFieldLabelOsc($i);
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    public function moreFields()
    {
        $moreFields = [
            'shipping_methods' => __('Shipping Method'),
            'payment_methods' => __('Payment Method'),
            'coupons' => __('Apply Coupon')
        ];

        if ($this->helperData->isModuleOutputEnabled('Mageplaza_Osc')) {
            $moreFields['giftwraps'] = __('Gift Wrap');
        }

        return $moreFields;
    }

    /**
     * @return array
     */
    public function popupBlock()
    {
        return [
            'shipping' => [__('Shipping Address Fields'), $this->addressFields()],
            'billing' => [__('Billing Address Fields'), $this->addressFields()],
            'more' => [__('More Fields'), $this->moreFields()]
        ];
    }

    /**
     *
     * @return boolean
     */
    public function checkModuleOsc()
    {
        return $this->moduleList->has('Mageplaza_Osc');
    }
}
