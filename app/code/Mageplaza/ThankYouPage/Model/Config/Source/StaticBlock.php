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

namespace Mageplaza\ThankYouPage\Model\Config\Source;

use Magento\Cms\Model\ResourceModel\Block\Collection;
use Magento\Framework\Option\ArrayInterface;

/**
 * Class StaticBlock
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class StaticBlock implements ArrayInterface
{
    const DISABLE = '0';

    /**
     * @var Collection
     */
    protected $cmsCollection;

    /**
     * StaticBlock constructor.
     *
     * @param Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->cmsCollection = $collection;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $collection = $this->cmsCollection->toOptionArray();
        $option     = [
            [
                'value' => self::DISABLE,
                'label' => __('Disable')
            ]
        ];

        $option = array_merge($option, $collection);

        return $option;
    }
}
