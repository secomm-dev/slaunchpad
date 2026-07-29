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
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoFilter\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Mirasvit\SeoFilter\Api\Data\AttributeConfigInterface;

class AttributeEnableSeoSource implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => AttributeConfigInterface::SEO_STATUS_DEFAULT,
                'label' => __('Use Default'),
            ],
            [
                'value' => AttributeConfigInterface::SEO_STATUS_ENABLED,
                'label' => __('Enabled'),
            ],
            [
                'value' => AttributeConfigInterface::SEO_STATUS_DISABLED,
                'label' => __('Disabled'),
            ],
        ];
    }
}
