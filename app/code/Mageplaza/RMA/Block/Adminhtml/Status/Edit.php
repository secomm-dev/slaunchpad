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

namespace Mageplaza\RMA\Block\Adminhtml\Status;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Status;

/**
 * Class Edit
 * @package Mageplaza\RMA\Block\Adminhtml\Status
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
     * @var HelperData
     */
    protected $_helperData;

    /**
     * constructor
     *
     * @param Registry $coreRegistry
     * @param Context $context
     * @param HelperData $helperData
     * @param array $data
     */
    public function __construct(
        Registry $coreRegistry,
        Context $context,
        HelperData $helperData,
        array $data = []
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->_helperData = $helperData;

        parent::__construct($context, $data);
    }

    /**
     * Initialize Status edit block
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_blockGroup = 'Mageplaza_RMA';
        $this->_controller = 'adminhtml_status';

        parent::_construct();
        /** @var Status $status */
        $status = $this->coreRegistry->registry('mageplaza_rma_status');
        if ($status->getId() === $this->_helperData->getRequestConfig('default_status')) {
            $this->buttonList->remove('delete');
        }

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
     * Retrieve text for header element depending on loaded Status
     *
     * @return string
     */
    public function getHeaderText()
    {
        /** @var Status $status */
        $status = $this->coreRegistry->registry('mageplaza_rma_status');
        if ($status->getId()) {
            return __("Edit Status '%1'", $this->escapeHtml($status->getName()));
        }

        return __('New Status');
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        /** @var Status $status */
        $status = $this->coreRegistry->registry('mageplaza_rma_status');
        if ($statusId = $status->getId()) {
            return $this->getUrl('*/*/save', ['id' => $statusId]);
        }

        return parent::getFormActionUrl();
    }
}
