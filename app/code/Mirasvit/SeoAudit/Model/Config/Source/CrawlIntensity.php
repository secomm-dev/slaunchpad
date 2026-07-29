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

namespace Mirasvit\SeoAudit\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CrawlIntensity implements OptionSourceInterface
{
    const LEVEL_HIGH   = 3;
    const LEVEL_MEDIUM = 2;
    const LEVEL_LOW    = 1;
    const LEVEL_CUSTOM = 0;

    public function toOptionArray(): array
    {
        return [
            ['value' => self::LEVEL_HIGH,   'label' => __('High — intensive crawl')],
            ['value' => self::LEVEL_MEDIUM, 'label' => __('Medium — default mode')],
            ['value' => self::LEVEL_LOW,    'label' => __('Low — soft crawl')],
            ['value' => self::LEVEL_CUSTOM, 'label' => __('Custom — expert mode')],
        ];
    }
}
