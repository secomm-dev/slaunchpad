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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Block\Adminhtml\ShippingLabel;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;
use Mageplaza\RMA\Model\ShippingLabel;

/**
 * Class Edit
 * @package Mageplaza\RMA\Block\Adminhtml\ShippingLabel
 */
class Edit extends Container
{
    /**
     * Core registry
     *
     * @var Registry
     */
    public $coreRegistry;

    /**
     * Edit constructor.
     *
     * @param Registry $coreRegistry
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Registry $coreRegistry,
        Context $context,
        array $data = []
    ) {
        $this->coreRegistry = $coreRegistry;

        parent::__construct($context, $data);
    }

    /**
     * Initialize ShippingLabel edit block
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_blockGroup = 'Mageplaza_RMA';
        $this->_controller = 'adminhtml_shippingLabel';

        parent::_construct();

        $this->buttonList->add(
            'save-and-continue',
            [
                'label' => __('Save and Continue Edit'),
                'class' => 'save',
                'data_attribute' => [
                    'mage-init' => [
                        'button' => [
                            'event' => 'saveAndContinueEdit',
                            'target' => '#edit_form'
                        ]
                    ]
                ]
            ],
            -100
        );
    }

    /**
     * Retrieve text for header element depending on loaded ShippingLabel
     *
     * @return string
     */
    public function getHeaderText()
    {
        /** @var ShippingLabel $shippingLabel */
        $shippingLabel = $this->coreRegistry->registry('mageplaza_rma_shipping_label');
        if ($shippingLabel->getId()) {
            return __("Edit Shipping Label '%1'", $this->escapeHtml($shippingLabel->getLabel()));
        }

        return __('New Shipping Label');
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        /** @var ShippingLabel $shippingLabel */
        $shippingLabel = $this->coreRegistry->registry('mageplaza_rma_shipping_label');
        if ($shippingLabelId = $shippingLabel->getId()) {
            return $this->getUrl('*/*/save', ['id' => $shippingLabelId]);
        }

        return parent::getFormActionUrl();
    }
}
