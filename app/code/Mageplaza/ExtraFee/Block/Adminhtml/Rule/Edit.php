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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Block\Adminhtml\Rule;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;
use Mageplaza\ExtraFee\Model\Rule;

/**
 * Class Edit
 * @package Mageplaza\ExtraFee\Block\Adminhtml\Rule
 */
class Edit extends Container
{
    /**
     * @var string
     */
    protected $_objectId = 'rule_id';

    /**
     * Core registry
     *
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * constructor
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
     * Get form action URL
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        /** @var Rule $rule */
        $rule = $this->coreRegistry->registry('mageplaza_extrafee_rule');
        if ($id = $rule->getId()) {
            return $this->getUrl('*/*/save', ['rule_id' => $id]);
        }

        return parent::getFormActionUrl();
    }

    /**
     * Initialize Rule edit block
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_blockGroup = 'Mageplaza_ExtraFee';
        $this->_controller = 'adminhtml_rule';

        parent::_construct();

        $this->buttonList->add('save-and-continue', [
            'label'          => __('Save and Continue Edit'),
            'class'          => 'save',
            'data_attribute' => [
                'mage-init' => [
                    'button' => [
                        'event'  => 'saveAndContinueEdit',
                        'target' => '#edit_form'
                    ]
                ]
            ]
        ], -100);
    }
}
