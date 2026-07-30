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

use Magento\Framework\Data\OptionSourceInterface;
use Mirasvit\SeoFilter\Model\ConfigProvider;

class RewriteLabelsSource implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => ConfigProvider::REWRITES_PER_STORE,
                'label' => __('Store View Label'),
            ],
            [
                'value' => ConfigProvider::REWRITES_DEFAULT_STORE,
                'label' => __('Default (Admin) Label'),
            ],
        ];
    }
}
