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

use Magento\Framework\Module\Manager;
use Magento\Framework\Option\ArrayInterface;

/**
 * Class EnableFAQ
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class EnableFAQ implements ArrayInterface
{
    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * EnableFAQ constructor.
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
        $option = [['value' => 0, 'label' => __('No')]];

        if ($this->moduleManager->isOutputEnabled('Mageplaza_Faqs')) {
            $option[] = [
                'value' => 1,
                'label' => __('Yes')
            ];
        }

        return $option;
    }
}
