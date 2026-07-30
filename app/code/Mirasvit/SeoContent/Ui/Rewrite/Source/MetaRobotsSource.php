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

namespace Mirasvit\SeoContent\Ui\Rewrite\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Mirasvit\SeoContent\Api\Data\RewriteInterface;

class MetaRobotsSource implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => RewriteInterface::META_ROBOTS_DEFAULT, 'label' => __('Don\'t change')],
            ['value' => RewriteInterface::META_ROBOTS_NOINDEX_NOFOLLOW, 'label' => __('NOINDEX, NOFOLLOW')],
            ['value' => RewriteInterface::META_ROBOTS_NOINDEX_FOLLOW, 'label' => __('NOINDEX, FOLLOW')],
            ['value' => RewriteInterface::META_ROBOTS_INDEX_NOFOLLOW, 'label' => __('INDEX, NOFOLLOW')],
            ['value' => RewriteInterface::META_ROBOTS_INDEX_FOLLOW, 'label' => __('INDEX, FOLLOW')],
        ];
    }
}
