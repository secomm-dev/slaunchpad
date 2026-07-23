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
 * @package     Mageplaza_OscUltimate
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscUltimate\Block\Adminhtml\Layout;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Widget\Context;
use Mageplaza\OscUltimate\Helper\Data;

/**
 * Class AbstractField
 * @package Mageplaza\OscUltimate\Block\Adminhtml\Layout
 */
abstract class AbstractManageFields extends Template
{
    const BLOCK_ID = '';

    /**
     * @var Data
     */
    protected $helper;
    /**
     * @var string
     */
    protected $_template = 'Mageplaza_OscUltimate::block/manageFields.phtml';

    /**
     * AbstractManageFields constructor.
     *
     * @param Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve the header text
     *
     * @return string
     */
    abstract public function getBlockTitle();

    /**
     * @return string
     */
    public function getBlockId()
    {
        return static::BLOCK_ID;
    }

    /**
     * @return bool
     */
    public function isVisible()
    {
        return true;
    }

}
