<?php

namespace Secomm\AddressDropdown\Plugin\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessor;
use Psr\Log\LoggerInterface;

class LayoutProcessorPlugin
{
    public function __construct(
        protected LoggerInterface $logger
    )
    {
        $this->logger = $logger;
    }

    public function afterProcess(LayoutProcessor $subject, array $jsLayout)
    {
        $customAttributeCode = 'sub_city';

        // Define the custom field for the shipping address
        $customFieldShipping = [
            'component' => 'Magento_Ui/js/form/element/abstract',
            'config' => [
                'customScope' => 'shippingAddress.custom_attributes',
                'customEntry' => null,
                'template' => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input',
            ],
            'dataScope' => 'shippingAddress.custom_attributes' . '.' . $customAttributeCode,
            'label' => __('Sub City'),
            'provider' => 'checkoutProvider',
            'sortOrder' => 110,
            'validation' => [
                'required-entry' => false
            ],
            'options' => [],
            'filterBy' => null,
            'customEntry' => null,
            'visible' => true,
        ];

        // Define the custom field for the billing address
        $customFieldBilling = [
            'component' => 'Magento_Ui/js/form/element/abstract',
            'config' => [
                'customScope' => 'billingAddress.custom_attributes',
                'customEntry' => null,
                'template' => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input',
            ],
            'dataScope' => 'billingAddress.custom_attributes' . '.' . $customAttributeCode,
            'label' => __('Sub City'),
            'provider' => 'checkoutProvider',
            'sortOrder' => 110,
            'validation' => [
                'required-entry' => false
            ],
            'options' => [],
            'filterBy' => null,
            'customEntry' => null,
            'visible' => true,
        ];

        // Add the custom field to the shipping address form
        $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset']['children'][$customAttributeCode] = $customFieldShipping;

        // Add the custom field to the billing address form
        $billingAddressComponents = &$jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['payments-list']['children'];

        foreach ($billingAddressComponents as &$billingComponent) {
            if (isset($billingComponent['component']) && $billingComponent['component'] == 'Magento_Checkout/js/view/billing-address') {
                $billingComponent['children']['form-fields']['children'][$customAttributeCode] = $customFieldBilling;
            }
        }

        return $jsLayout;
    }
}
