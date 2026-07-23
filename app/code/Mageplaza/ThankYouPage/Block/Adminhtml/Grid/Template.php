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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Block\Adminhtml\Grid;

use Magento\Backend\Block\Widget\Button\SplitButton;
use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;

/**
 * Class Template
 * @package Mageplaza\ThankYouPage\Block\Adminhtml\Grid
 */
class Template extends Container
{
    /**
     * @var Pagetype
     */
    protected $pagetype;

    /**
     * Template constructor.
     *
     * @param Context $context
     * @param Pagetype $pagetype
     * @param array $data
     */
    public function __construct(
        Context $context,
        Pagetype $pagetype,
        array $data = []
    ) {
        $this->pagetype = $pagetype;

        parent::__construct($context, $data);
    }

    /**
     * Prepare button and grid
     *
     * @return Container
     */
    protected function _prepareLayout()
    {
        $addButtonProps = [
            'id'           => 'add_new_template',
            'label'        => __('Add Rule'),
            'class'        => 'add',
            'button_class' => '',
            'class_name'   => SplitButton::class,
            'options'      => $this->_getAddProductButtonOptions(),
        ];
        $this->buttonList->add('add_new', $addButtonProps);

        return parent::_prepareLayout();
    }

    /**
     * Retrieve options for 'Add Product' split button
     *
     * @return array
     */
    protected function _getAddProductButtonOptions()
    {
        $splitButtonOptions = [];
        $types              = $this->pagetype->getPageType();

        foreach ($types as $typeId => $typeLabel) {
            $splitButtonOptions[$typeId] = [
                'label'   => $typeLabel,
                'onclick' => "setLocation('" . $this->_getTemplateCreateUrl($typeId) . "')",
                'default' => Pagetype::DEFAULT_TYPE_PAGE == $typeId,
            ];
        }

        return $splitButtonOptions;
    }

    /**
     * Retrieve product create url by specified product type
     *
     * @param string $type
     *
     * @return string
     */
    protected function _getTemplateCreateUrl($type)
    {
        return $this->getUrl(
            'mpthankyoupage/*/new',
            ['page_type' => $type]
        );
    }

    /**
     * Check whether it is single store mode
     *
     * @return bool
     */
    public function isSingleStoreMode()
    {
        return $this->_storeManager->isSingleStoreMode();
    }
}
