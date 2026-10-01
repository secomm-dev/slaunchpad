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

namespace Mageplaza\RMA\Model\Config\Source\System\Policy;

use Magento\Cms\Model\ResourceModel\Page\Collection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Option\ArrayInterface;

/**
 * Class Item
 * @package Mageplaza\RMA\Model\Config\Source\System\Policy
 */
class Item implements ArrayInterface
{
    /**
     * @var CollectionFactory
     */
    protected $_pageColFactory;

    /**
     * Policy constructor.
     *
     * @param CollectionFactory $blockColFactory
     */
    public function __construct(CollectionFactory $blockColFactory)
    {
        $this->_pageColFactory = $blockColFactory;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        /** @var Collection $cmsPageCol */
        $cmsPageCol = $this->_pageColFactory->create();
        $emptyOption = [
            'value' => 0,
            'label' => __('-- Please select --')
        ];
        foreach ($cmsPageCol as $cmsPage) {
            $options[] = [
                'value' => $cmsPage->getIdentifier(),
                'label' => $cmsPage->getTitle()
            ];
        }
        array_unshift($options, $emptyOption);

        return $options;
    }
}
