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



namespace Mirasvit\SeoContent\Ui\Rewrite\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Mirasvit\SeoContent\Api\Data\RewriteInterface;

class AddToSitemapSource implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => RewriteInterface::SITEMAP_DEFAULT, 'label' => __('Default')],
            ['value' => RewriteInterface::SITEMAP_YES, 'label' => __('Yes')],
            ['value' => RewriteInterface::SITEMAP_NO, 'label' => __('No')],
        ];
    }
}
