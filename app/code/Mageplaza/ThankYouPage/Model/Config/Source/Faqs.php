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

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager;
use Magento\Framework\Option\ArrayInterface;
use Mageplaza\Faqs\Model\Category;
use Mageplaza\Faqs\Model\ResourceModel\Category\Collection;

/**
 * Class Faqs
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class Faqs implements ArrayInterface
{
    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * Faqs constructor.
     *
     * @param Manager $moduleManager
     */
    public function __construct(Manager $moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $option = [];

        if ($this->moduleManager->isOutputEnabled('Mageplaza_Faqs')) {
            $faqs = ObjectManager::getInstance()->create(Category::class);
            /** @var Collection $collection */
            $collection = $faqs->getCollection();
            foreach ($collection as $category) {
                $option[] = [
                    'value' => $category->getId(),
                    'label' => __($category->getName())
                ];
            }
        }

        return $option;
    }
}
