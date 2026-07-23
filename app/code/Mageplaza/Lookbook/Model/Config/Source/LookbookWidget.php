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

namespace Mageplaza\Lookbook\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Mageplaza\Lookbook\Model\ResourceModel\Lookbook\CollectionFactory;
use Mageplaza\Lookbook\Model\ResourceModel\Lookbook\Collection;

/**
 * Class LookbookWidget
 * @package Mageplaza\Lookbook\Model\Config\Source
 */
class LookbookWidget implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * LookbookWidget constructor.
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
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->addActiveFilter();
        $options = [];
        foreach ($collection as $model) {
            $options[] = [
                'value' => $model->getId(),
                'label' => $model->getName()
            ];
        }

        return $options;
    }
}
