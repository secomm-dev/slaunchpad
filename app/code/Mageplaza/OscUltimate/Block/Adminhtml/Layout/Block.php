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

use Mageplaza\Osc\Helper\Data;

/**
 * Class Block
 * @package Mageplaza\OscUltimate\Block\Adminhtml\Layout
 */
class Block extends AbstractBlock
{
    const BLOCK_ID                   = 'mposc-layout-block';
    const ONE_COLUMN                 = '1column';
    const TWO_COLUMNS                = '2columns';
    const TWO_COLUMNS_FLOATING       = '2columns-floating';
    const THREE_COLUMNS              = '3columns';
    const THREE_COLUMNS_WITH_COLSPAN = '3columns-colspan';

    /**
     * @return string
     */
    public function getBlockTitle()
    {
        return (string) __('Manage Block');
    }

    /**
     * @return array[]
     */
    public function getLayoutCheckoutPage()
    {
        $options = [
            [
                'label' => __('1 Column'),
                'value' => self::ONE_COLUMN
            ],
            [
                'label' => __('2 Columns'),
                'value' => self::TWO_COLUMNS
            ],
            [
                'label' => __('2 Columns With Floating Column'),
                'value' => self::TWO_COLUMNS_FLOATING
            ],
            [
                'label' => __('3 Columns'),
                'value' => self::THREE_COLUMNS
            ],
            [
                'label' => __('3 Columns With Colspan'),
                'value' => self::THREE_COLUMNS_WITH_COLSPAN
            ]
        ];

        return $options;
    }

    /**
     * @return array
     */
    public function getDesignConfiguration()
    {
        $pageLayout = $this->getHelperData()->getConfigValue(Data::CONFIG_DISPLAY_PAGE_LAYOUT);

        return $pageLayout;
    }

    /**
     * @return int
     */
    public function checkUseDefault()
    {
        if ($this->getHelperData()->getConfigValue(Data::SORTED_BLOCK_POSITION)) {
            return 0;
        }

        return 1;
    }

    /**
     * @return string
     */
    public function getSystemConfigLayout()
    {
        return $this->getHelperData()->getSystemValue();
    }
}
