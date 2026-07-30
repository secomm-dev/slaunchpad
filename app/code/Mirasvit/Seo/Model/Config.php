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

namespace Mirasvit\Seo\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Model\Cookie\Cookie;

/**
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class Config
{
    const TRAILING_SLASH_DISABLE = 0;
    const NO_TRAILING_SLASH      = 1;
    const TRAILING_SLASH         = 2;

    const META_TITLE_MAX_LENGTH       = 70;
    const META_DESCRIPTION_MAX_LENGTH = 160;

    const URL_FORMAT_SHORT = 1;
    const URL_FORMAT_LONG  = 2;

    const NOINDEX_NOFOLLOW = 1;
    const NOINDEX_FOLLOW   = 2;
    const INDEX_NOFOLLOW   = 3;
    const INDEX_FOLLOW     = 4;

    const PRODUCTS_WITH_REVIEWS_NUMBER = 1;
    const REVIEWS_NUMBER               = 2;
    const OPENGRAPH_LOGO_IMAGE         = 1;
    const OPENGRAPH_PRODUCT_IMAGE      = 2;
    const INFO_IP                      = 1;
    const INFO_COOKIE                  = 2;
    const COOKIE_DEL_BUTTON            = 'Delete cookie';

    const CANONICAL_LAYERED_NOFILTERS = 0;
    const CANONICAL_LAYERED_ALL       = 1;
    const CANONICAL_LAYERED_CONFIG    = 2;

    const CANONICAL_LAYERED_USAGE_ALL = 'all';
    const CANONICAL_LAYERED_USAGE_ONE = 'one';

    const PRODUCT_BREADCRUMBS_APPEARANCE_DEFAULT       = 0;
    const PRODUCT_BREADCRUMBS_APPEARANCE_DEFAULT_FIRST = 1;
    const PRODUCT_BREADCRUMBS_APPEARANCE_CUSTOM        = 2;

    const PRODUCT_BREADCRUMBS_FORMAT_DIRECT   = 0;
    const PRODUCT_BREADCRUMBS_FORMAT_SHORTEST = 1;
    const PRODUCT_BREADCRUMBS_FORMAT_LONGEST  = 2;

    const PRODUCT_BREADCRUMBS_BRAND_FORMAT_BRAND_ONLY     = 0;
    const PRODUCT_BREADCRUMBS_BRAND_FORMAT_CATEGORY_BRAND = 1;

    const PRODUCT_CANONICAL_URL_FORMAT_SHORT = 0;
    const PRODUCT_CANONICAL_URL_FORMAT_LONG  = 1;


    //seo template rule

    // open graph

    const COOKIE_ADD_BUTTON = 'Add cookie';
    const BYPASS_COOKIE     = 'info_bypass_cookie';

    //seo info

    private $scopeConfig;

    private $cookie;

    private $storeManager;

    private $serializer;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Cookie $cookie,
        StoreManagerInterface $storeManager,
        Json $serializer
    ) {
        $this->scopeConfig  = $scopeConfig;
        $this->cookie       = $cookie;
        $this->storeManager = $storeManager;
        $this->serializer   = $serializer;
    }

    public function isAutoRedirectOnDisableEnabled(?int $storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'seo/auto_redirect/enable_on_disable',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAutoRedirectOnDeleteEnabled(?int $storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'seo/auto_redirect/enable_on_delete',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAutoRedirectFallbackUrl(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'seo/auto_redirect/fallback_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAutoRedirectType(?int $storeId = null): int
    {
        $value = (int)$this->scopeConfig->getValue(
            'seo/auto_redirect/redirect_type',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value ?: \Mirasvit\Seo\Model\Config\Source\Redirect\RedirectType::PERMANENT301;
    }

    public function isAddCanonicalUrl(): bool
    {
        return (bool)$this->scopeConfig->getValue('seo/general/is_add_canonical_url');
    }

    public function isAddLongestCanonicalProductUrl(?int $storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'seo/general/is_longest_canonical_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAssociatedCanonicalConfigurableProduct(): int
    {
        return (int)$this->scopeConfig->getValue('seo/general/associated_canonical_configurable_product');
    }

    public function getAssociatedCanonicalGroupedProduct(): int
    {
        return (int)$this->scopeConfig->getValue('seo/general/associated_canonical_grouped_product');
    }

    public function getAssociatedCanonicalBundleProduct(): int
    {
        return (int)$this->scopeConfig->getValue('seo/general/associated_canonical_bundle_product');
    }

    public function getCanonicalLayeredType(): int
    {
        return (int)$this->scopeConfig->getValue('seo/general/canonical_layered');
    }

    public function getCanonicalLayeredConfig(): array
    {
        $config = $this->scopeConfig->getValue('seo/general/canonical_layered_config');

        $prepared = [];

        if (!$config || $config == '[]') {
            return $prepared;
        }

        try {
            $config = $this->serializer->unserialize($config);
        } catch (\Exception $e) {
            $config = null;
        }

        foreach ((array)$config as $item) {
            $prepared[$item['attribute']] = $item['usage'];
        }

        return $prepared;
    }

    public function getCanonicalStoreWithoutStoreCode(?int $store = null): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/general/canonical_store_without_store_code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getCrossDomainStore(?int $store = null): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/general/crossdomain',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function isPreferCrossDomainHttps(?int $store = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'seo/general/crossdomain_prefer_https',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function isPaginatedCanonical(): bool
    {
        return (bool)$this->scopeConfig->getValue('seo/general/paginated_canonical');
    }

    public function getCanonicalUrlIgnorePages(): array
    {
        $pages = (string)$this->scopeConfig->getValue('seo/general/canonical_url_ignore_pages');
        $pages = explode("\n", trim($pages));
        $pages = array_map('trim', $pages);

        return $pages;
    }

    public function getNoindexPages(?int $store = null): array
    {
        if (empty($store)) {
            $store = $this->storeManager->getStore()->getId();
        }

        $storePages = $this->getOptionData(
            (string)$this->scopeConfig->getValue(
                'seo/general/noindex_pages2',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $store
            )
        );

        $generalPages = $this->getOptionData(
            (string)$this->scopeConfig->getValue('seo/general/noindex_pages2')
        );

        $pages = array_merge($storePages, $generalPages);

        $result = [];

        foreach ($pages as $value) {
            if (!is_array($value)) {
                continue;
            }

            $result[] = new \Magento\Framework\DataObject($value);
        }

        return $result;
    }

    public function getHttpsNoindexPages(): int
    {
        return (int)$this->scopeConfig->getValue('seo/general/https_noindex_pages');
    }

    public function isPagingPrevNextEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue('seo/general/is_paging_prevnext');
    }

    public function isCategoryMetaTagsUsed(): bool
    {
        return (bool)$this->scopeConfig->getValue('seo/general/is_category_meta_tags_used');
    }

    public function isProductMetaTagsUsed(): bool
    {
        return (bool)$this->scopeConfig->getValue('seo/general/is_product_meta_tags_used');
    }

    public function isUseHtmlSymbolsInMetaTags(): bool
    {
        return (bool)$this->scopeConfig->getValue('seo/general/is_use_html_symbols_in_meta_tags');
    }

    public function isUseShortDescriptionForCategories(): bool
    {
        return (bool)$this->scopeConfig->getValue('seo/general/is_use_short_description_for_categories');
    }

    public function getMetaDescriptionPageNumber(int $store): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/extended/meta_description_page_number',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getMetaTitleMaxLength(int $store): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/extended/meta_title_max_length',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getMetaDescriptionMaxLength(int $store): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/extended/meta_description_max_length',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getProductNameMaxLength(int $store): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/extended/product_name_max_length',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getProductShortDescriptionMaxLength(int $store): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/extended/product_short_description_max_length',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function isRedirectToLowercaseEnabled(int $store): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'seo/extended/redirect_to_lowercase',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getAllowedLowercasePageTypes(int $store): array
    {
        $rawData = $this->scopeConfig->getValue(
            'seo/extended/to_lowercase_allowed_types',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
        try {
            $data = (array)$this->serializer->unserialize((string)$rawData);
        } catch (\Exception $e) {
            $data = [];
        }

        $result = [];
        foreach ($data as $item) {
            if (isset($item['expression'])) {
                $result[] = $item['expression'];
            }
        }

        return $result;
    }

    /**
     * SEO URL
     */
    public function isEnabledSeoUrls(): bool
    {
        return (bool)$this->scopeConfig->getValue('seo/url/layered_navigation_friendly_urls');
    }

    public function getTrailingSlash(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/url/trailing_slash',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductUrlFormat(): int
    {
        return (int)$this->scopeConfig->getValue('seo/url/product_url_format');
    }

    public function getProductUrlKey(int $store): string
    {
        return (string)$this->scopeConfig->getValue(
            'seo/url/product_url_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function isEnabledRemoveParentCategoryPath(?int $storeId = null, ?int $websiteId = null): bool
    {
        if ($websiteId) {
            return (bool)$this->scopeConfig->getValue(
                'seo/url/use_category_short_url',
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                $websiteId
            );
        }

        return (bool)$this->scopeConfig->getValue(
            'seo/url/use_category_short_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * INFO
     */
    public function isInfoEnabled(?int $storeId = null): bool
    {
        if (!$this->_isInfoAllowed()) {
            return false;
        }

        return (bool)$this->scopeConfig->getValue(
            'seo/info/info',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isShowAltLinkInfo(?int $storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'seo/info/alt_link_info',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isShowTemplatesRewriteInfo(?int $storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'seo/info/templates_rewrite_info',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if "Use Categories Path for Product URLs" enabled
     */
    public function isProductLongUrlEnabled(int $storeId): bool
    {
        return (bool)$this->scopeConfig->getValue(
            \Magento\Catalog\Helper\Product::XML_PATH_PRODUCT_URL_USE_CATEGORY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAddStoreCodeToUrlsEnabled(): string
    {
        return (string)$this->scopeConfig->getValue(\Magento\Store\Model\Store::XML_PATH_STORE_IN_URL);
    }

    public function isApplyUrlKeyForNewProducts(int $storeId): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'seo/url/apply_url_key_for_new_products',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    protected function _isInfoAllowed(?int $storeId = null): bool
    {
        $info = $this->scopeConfig->getValue(
            'seo/info/info',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (($info == self::INFO_COOKIE)
            && $this->cookie->isCookieExist()) {
            return true;
        } elseif ($info == self::INFO_IP) {
            $ips = (string)$this->scopeConfig->getValue(
                'seo/info/allowed_ip',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            );
            if ($ips == '') {
                return true;
            }
            if (!isset($_SERVER['REMOTE_ADDR'])) {
                return false;
            }
            $ips = explode(',', $ips);
            $ips = array_map('trim', $ips);

            return in_array($_SERVER['REMOTE_ADDR'], $ips);
        }

        return false;
    }

    private function getOptionData(string $data): array
    {
        $result = [];

        if ($decode = json_decode($data, true)) {
            $result = $decode;
        } else {
            try {
                $result = $this->serializer->unserialize($data);
            } catch (\Exception $e) {
                $result = null;
            }
            if (!$result) {
                $result = [0 => $data];
            }
        }

        return $result;
    }

    public function isUseCategoryCanonicalTag(int $storeId): bool
    {
        return (bool)$this->scopeConfig->getValue(
            \Magento\Catalog\Helper\Category::XML_PATH_USE_CATEGORY_CANONICAL_TAG,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isUseProductCanonicalTag(int $storeId): bool
    {
        return (bool)$this->scopeConfig->getValue(
            \Magento\Catalog\Helper\Product::XML_PATH_USE_PRODUCT_CANONICAL_TAG,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductBreadcrumbsAppearance(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/product_breadcrumbs/appearance',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductBreadcrumbsFormat(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/product_breadcrumbs/format',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductBreadcrumbsIncludeBrand(?int $storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'seo/product_breadcrumbs/include_brand',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductBreadcrumbsBrandFormat(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo/product_breadcrumbs/brand_format',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
