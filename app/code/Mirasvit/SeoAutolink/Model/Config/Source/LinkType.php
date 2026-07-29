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


declare(strict_types=1);

namespace Mirasvit\SeoAutolink\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Mirasvit\SeoAutolink\Api\Data\LinkInterface;

class LinkType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => LinkInterface::LINK_TYPE_URL,      'label' => __('Custom URL')],
            ['value' => LinkInterface::LINK_TYPE_PRODUCT,  'label' => __('Product by ID')],
            ['value' => LinkInterface::LINK_TYPE_CATEGORY, 'label' => __('Category by ID')],
        ];
    }
}
