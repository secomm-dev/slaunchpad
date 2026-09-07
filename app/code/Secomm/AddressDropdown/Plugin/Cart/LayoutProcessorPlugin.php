<?php

namespace Secomm\AddressDropdown\Plugin\Cart;

use Magento\Checkout\Block\Cart\LayoutProcessor;
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
        $fieldsAttribute = ['city', 'custom_city'];
        $template = 'ui/form/field';
        $cityField = [
            'component' => 'Magento_Ui/js/form/element/abstract',
            'config' => [
                'customScope' => 'shippingAddress.custom_attributes.city',
                'customEntry' => null,
                'template' => $template,
                'elementTmpl' => 'ui/form/element/input',
            ],
            'dataScope' => 'shippingAddress.custom_attributes.city',
            'label' => __('City'),
            'provider' => 'checkoutProvider',
            'sortOrder' => 114,
            'validation' => [
                'required-entry' => false
            ],
            'options' => [],
            'filterBy' => null,
            'customEntry' => null,
            'visible' => false,
        ];

        $customCityField = [
            'component' => 'Magento_Ui/js/form/element/select',
            'config' => [
                'customScope' => 'shippingAddress.custom_attributes.custom_city',
                'customEntry' => null,
                'template' => $template,
                'elementTmpl' => 'ui/form/element/select',
            ],
            'dataScope' => 'shippingAddress.custom_attributes.custom_city',
            'label' => __('City'),
            'provider' => 'checkoutProvider',
            'sortOrder' => 114,
            'validation' => [
                'required-entry' => false
            ],
            'options' => [
                [
                    'value' => '',
                    'label' => __('Please select a city'),
                ]
            ],
            'filterBy' => null,
            'customEntry' => null,
            'visible' => false,
        ];

        $jsLayout['components']['block-summary']['children']['block-shipping']['children']['address-fieldsets']['children'][$fieldsAttribute[0]] = $cityField;
        $jsLayout['components']['block-summary']['children']['block-shipping']['children']['address-fieldsets']['children'][$fieldsAttribute[1]] = $customCityField;

        return $jsLayout;
    }
}
