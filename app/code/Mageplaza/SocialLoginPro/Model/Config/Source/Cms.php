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
 * @category  Mageplaza
 * @package   Mageplaza_SocialLoginPro
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\SocialLoginPro\Model\Config\Source;

use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Page\Collection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Option\ArrayInterface;

/**
 * Class Cms
 *
 * @package Mageplaza\SocialLoginPro\Model\Config\Source
 */
class Cms implements ArrayInterface
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * Cms constructor.
     *
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options    = [];
        $collection = $this->collectionFactory->create();
        /**
         * @var Collection $collection
         */
        $collection->addFieldToFilter('is_active', Page::STATUS_ENABLED);

        foreach ($collection as $page) {
            $data['value'] = $page->getData('identifier');
            $data['label'] = $page->getData('title');
            $options[]     = $data;
        }

        return $options;
    }
}
