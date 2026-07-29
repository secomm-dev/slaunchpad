<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Seo\Model\Config\Source\Alternate;

use Magento\Framework\Option\ArrayInterface;
use Mirasvit\Seo\Api\Config\AlternateConfigInterface as AlternateConfig;

class Alternate implements ArrayInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => __('Disabled')],
            ['value' => AlternateConfig::ALTERNATE_DEFAULT, 'label' => __('Enabled for all websites')],
            ['value' => AlternateConfig::ALTERNATE_CONFIGURABLE, 'label' => __('Configure')],
        ];
    }

}
