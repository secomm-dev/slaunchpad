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

namespace Mirasvit\SeoMarkup\Model\Config;

use Magento\Store\Model\ScopeInterface;
use Mirasvit\SeoMarkup\Model\Config;

class SearchboxConfig extends Config
{
    const SEARCH_BOX_TYPE_CATALOG_SEARCH = 1;
    const SEARCH_BOX_TYPE_BLOG_SEARCH    = 2;

    public function getSearchBoxType(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            'seo_markup/searchbox/searchbox_type',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getBlogSearchUrl(): ?string
    {
        return $this->scopeConfig->getValue('seo_markup/searchbox/blog_search_url');
    }
}
