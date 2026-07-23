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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Block\Adminhtml\Lookbook\Edit\Render;

/**
 * Class Image
 * @package Mageplaza\Lookbook\Block\Adminhtml\Lookbook\Edit\Render
 */
class Image extends \Mageplaza\Core\Block\Adminhtml\Renderer\Image
{
    /**
     * @return string
     */
    protected function _getDeleteCheckbox()
    {
        $html = '';
        if ($this->getValue()) {
            return $html . $this->_getHiddenInput();
        }

        return $html;
    }
}
