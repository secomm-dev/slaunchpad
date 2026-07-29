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

namespace Mirasvit\SeoSitemap\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Mirasvit\SeoSitemap\Model\Config;

class PaginationPositionSource implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::PAGINATION_POSITION_TOP, 'label' => __('Top of page')],
            ['value' => Config::PAGINATION_POSITION_BOTTOM, 'label' => __('Bottom of page')],
            ['value' => Config::PAGINATION_POSITION_BOTH, 'label' => __('Both top and bottom')],
        ];
    }
}
