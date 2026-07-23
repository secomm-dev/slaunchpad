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

namespace Mageplaza\AbandonedCart\Block\Adminhtml\Report\CartBoard;

use Magento\Backend\Block\Template;

/**
 * Class Pagination
 * @package Mageplaza\AbandonedCart\Block\Adminhtml\Report\CartBoard
 */
class Pagination extends Template
{
    /**
     * @return array
     */
    public function getOptions()
    {
        return [
            ['value' => 20, 'label' => 20],
            ['value' => 30, 'label' => 30],
            ['value' => 50, 'label' => 50],
            ['value' => 100, 'label' => 100],
            ['value' => 200, 'label' => 200]
        ];
    }

    /**
     * @return int|mixed
     */
    public function getPageSize()
    {
        $mpFilter = $this->getRequest()->getParam('mpFilter');

        return $mpFilter['page_size'] ?? 20;
    }

    /**
     * @return int|mixed
     */
    public function getCurrentPage()
    {
        $mpFilter = $this->getRequest()->getParam('mpFilter');

        return $mpFilter['current_page'] ?? 1;
    }
}
