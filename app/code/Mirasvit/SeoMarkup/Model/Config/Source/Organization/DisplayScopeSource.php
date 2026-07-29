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

namespace Mirasvit\SeoMarkup\Model\Config\Source\Organization;

use Magento\Framework\Data\OptionSourceInterface;
use Mirasvit\SeoMarkup\Model\Config\OrganizationConfig;

class DisplayScopeSource implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => OrganizationConfig::DISPLAY_SCOPE_HOME, 'label' => __('Homepage only')],
            ['value' => OrganizationConfig::DISPLAY_SCOPE_ALL, 'label' => __('All CMS pages')],
            ['value' => OrganizationConfig::DISPLAY_SCOPE_SPECIFIC, 'label' => __('Specific CMS pages')],
        ];
    }
}
