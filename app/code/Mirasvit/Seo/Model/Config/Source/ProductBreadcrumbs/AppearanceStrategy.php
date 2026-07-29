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

namespace Mirasvit\Seo\Model\Config\Source\ProductBreadcrumbs;

use Magento\Framework\Data\OptionSourceInterface;
use Mirasvit\Seo\Model\Config;

class AppearanceStrategy implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::PRODUCT_BREADCRUMBS_APPEARANCE_DEFAULT, 'label' => __('Default')],
            ['value' => Config::PRODUCT_BREADCRUMBS_APPEARANCE_DEFAULT_FIRST, 'label' => __('Default first')],
            ['value' => Config::PRODUCT_BREADCRUMBS_APPEARANCE_CUSTOM, 'label' => __('Custom')],
        ];
    }
}
