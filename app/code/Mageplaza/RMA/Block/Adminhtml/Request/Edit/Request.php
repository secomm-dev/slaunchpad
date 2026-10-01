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

namespace Mageplaza\RMA\Block\Adminhtml\Request\Edit;

use Magento\Backend\Block\Template;

/**
 * Class Request
 * @package Mageplaza\RMA\Block\Adminhtml\Request\Edit
 */
class Request extends Template
{
    const TEMPLATE_INDEX_PAGE = 'index';
    const TEMPLATE_EDIT_PAGE = 'edit';

    /**
     * @return string
     */
    public function getTemplateIndexUrl()
    {
        return $this->getUrl('mprma/request/template_load', [
            'form_key' => $this->getFormKey(),
            'template_type' => self::TEMPLATE_INDEX_PAGE
        ]);
    }
}
