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
use Mageplaza\Productslider\Model\Config\Source\ProductTypeWidget;

/**
 * Class ProductSlider
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class ProductSlider implements ArrayInterface
{
    const DISABLE = '0';

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * ProductSlider constructor.
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
        $options = [['value' => self::DISABLE, 'label' => __('Disable')]];

        if ($this->moduleManager->isOutputEnabled('Mageplaza_Productslider')) {
            $object = ObjectManager::getInstance()->create(ProductTypeWidget::class);
            foreach ($object->toArray() as $value => $label) {
                $options[] = [
                    'value' => $value,
                    'label' => $label
                ];
            }
        }

        return $options;
    }
}
