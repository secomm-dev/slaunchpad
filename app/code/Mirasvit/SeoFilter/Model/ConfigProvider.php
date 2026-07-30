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

namespace Mirasvit\SeoFilter\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\ObjectManager;
use Mirasvit\SeoFilter\Api\Data\AttributeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\DataObject;

class ConfigProvider
{
    const NAME_SEPARATOR_NONE     = '';
    const NAME_SEPARATOR_DASH     = '_';
    const NAME_SEPARATOR_CAPITAL  = 'A';
    const NAME_SEPARATOR_HYPHEN   = '-';

    const URL_FORMAT_OPTIONS      = 'options';
    const URL_FORMAT_ATTR_OPTIONS = 'attr_options';
    
    const REWRITES_PER_STORE      = 'storeview';
    const REWRITES_DEFAULT_STORE  = 'default';
    
    const SEPARATOR_FILTER_VALUES = ',';
    const SEPARATOR_FILTERS       = '-';
    const SEPARATOR_DECIMAL       = ':';
    
    const URL_FORMAT_SHORT_DASH       = 'short_dash';
    const URL_FORMAT_SHORT_SLASH      = 'short_slash';
    const URL_FORMAT_LONG_DASH        = 'long_dash';
    const URL_FORMAT_LONG_SLASH       = 'long_slash';
    const URL_FORMAT_LONG_COLON       = 'long_colon';
    const URL_FORMAT_SHORT_UNDERSCORE = 'short_underscore';

    const URL_FORMAT_CONFIG = [
        self::URL_FORMAT_SHORT_DASH => [
            'format'              => self::URL_FORMAT_OPTIONS,
            'attribute_separator' => '-',
            'option_separator'    => '-'
        ],
        self::URL_FORMAT_SHORT_SLASH => [
            'format'              => self::URL_FORMAT_OPTIONS,
            'attribute_separator' => '/',
            'option_separator'    => '/'
        ],
        self::URL_FORMAT_SHORT_UNDERSCORE => [
            'format'              => self::URL_FORMAT_OPTIONS,
            'attribute_separator' => '_',
            'option_separator'    => '_'
        ],
        self::URL_FORMAT_LONG_DASH => [
            'format'              => self::URL_FORMAT_ATTR_OPTIONS,
            'attribute_separator' => '-',
            'option_separator'    => '-'
        ],
        self::URL_FORMAT_LONG_SLASH => [
            'format'              => self::URL_FORMAT_ATTR_OPTIONS,
            'attribute_separator' => '/',
            'option_separator'    => '-'
        ],
        self::URL_FORMAT_LONG_COLON => [
            'format'              => self::URL_FORMAT_ATTR_OPTIONS,
            'attribute_separator' => ':',
            'option_separator'    => ','
        ],
    ];

    const FILTER_STOCK  = 'mst_stock';
    const FILTER_SALE   = 'mst_on_sale';
    const FILTER_NEW    = 'mst_new_products';
    const FILTER_RATING = 'mst_rating';

    const LABEL_SALE_YES  = 'onsale';
    const LABEL_SALE_NO   = 'nosale';
    const LABEL_STOCK_IN  = 'instock';
    const LABEL_STOCK_OUT = 'outofstock';

    const LABEL_RATING_1 = 'rating1';
    const LABEL_RATING_2 = 'rating2';
    const LABEL_RATING_3 = 'rating3';
    const LABEL_RATING_4 = 'rating4';
    const LABEL_RATING_5 = 'rating5';

    const ATTRIBUTES_EXCEPTIONS = ['available_shipping_methods'];

    private $scopeConfig;

    private $request;

    private $moduleManager;

    private $enabledAttributeExists = null;

    private $attributesConfig = [];

    private $sliderAttributeCodesCache = null;

    private $displayModeSliderCache = [];

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        RequestInterface $request,
        ModuleManager $moduleManager
    ) {
        $this->scopeConfig   = $scopeConfig;
        $this->request       = $request;
        $this->moduleManager = $moduleManager;
    }

    public function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue('mst_seo_filter/general/is_enabled', ScopeInterface::SCOPE_STORE);
    }

    public function canProceed(): bool
    {
        $isEnabled = $this->isEnabled();

        if (!$isEnabled) {
            if (!is_null($this->enabledAttributeExists)) {
                return $this->enabledAttributeExists;
            }
            $isEnabled = $this->enabledAttributeExists = ObjectManager::getInstance()->create('Mirasvit\SeoFilter\Service\RewriteService')->enabledAttrubteExists();
        }

        return $isEnabled; 
    }

    public function isApplicable(): bool
    {
        return $this->canProceed()
            && in_array($this->request->getFullActionName(), [
                'catalog_category_view',
                'landing_landing_view',
                'all_products_page_index_index',
                'brand_brand_view',
            ]);
    }

    public function getUrlFormat(): string
    {
        $formatWithSeparator = $this->scopeConfig->getValue('mst_seo_filter/general/url_format_with_separator', ScopeInterface::SCOPE_STORE);

        if ($formatWithSeparator) {
            return self::URL_FORMAT_CONFIG[$formatWithSeparator]['format'];
        }

        $format = (string)$this->scopeConfig->getValue('mst_seo_filter/general/url_format', ScopeInterface::SCOPE_STORE);

        return $format ? $format : self::URL_FORMAT_OPTIONS;
    }

    public function getUrlFormatConfig(): DataObject
    {
        $format = (string)$this->scopeConfig->getValue('mst_seo_filter/general/url_format_with_separator', ScopeInterface::SCOPE_STORE);
     
        if (!$format) {
            $format = (string)$this->scopeConfig->getValue('mst_seo_filter/general/url_format', ScopeInterface::SCOPE_STORE) == self::URL_FORMAT_ATTR_OPTIONS
                ? self::URL_FORMAT_LONG_SLASH
                : self::URL_FORMAT_SHORT_DASH;
        }

        $attributeSeparator = self::URL_FORMAT_CONFIG[$format]['attribute_separator'];

        $optionSeparator = self::URL_FORMAT_CONFIG[$format]['option_separator'];
           
        return new DataObject([
            'format'              => $format,
            'attribute_separator' => $attributeSeparator,
            'option_separator'    => $optionSeparator,

        ]);
    }

    public function getNameSeparator(): string
    {
        return (string)$this->scopeConfig->getValue('mst_seo_filter/general/name_separator', ScopeInterface::SCOPE_STORE);
    }

    public function getPrefix(): string
    {
        return (string)$this->scopeConfig->getValue('mst_seo_filter/general/prefix', ScopeInterface::SCOPE_STORE);
    }

    public function isMultiselectEnabled(string $attributeCode): bool
    {
        if (
            class_exists('Mirasvit\LayeredNavigation\Model\ConfigProvider')
            && $this->moduleManager->isEnabled('Mirasvit_LayeredNavigation')
        ) {
            return ObjectManager::getInstance()->get('Mirasvit\LayeredNavigation\Model\ConfigProvider')->isMultiselectEnabled($attributeCode);
        }

        return false;
    }

    public function isDisplayModeSlider(string $attributeCode): bool
    {
        if (isset($this->displayModeSliderCache[$attributeCode])) {
            return $this->displayModeSliderCache[$attributeCode];
        }

        $result = false;

        if (
            class_exists('Mirasvit\LayeredNavigation\Repository\AttributeConfigRepository')
            && $this->moduleManager->isEnabled('Mirasvit_LayeredNavigation')
        ) {
            $attributeConfigRepository = ObjectManager::getInstance()->get('Mirasvit\LayeredNavigation\Repository\AttributeConfigRepository');
            $attributeConfig = $attributeConfigRepository->getByAttributeCode((string)$attributeCode);
            if ($attributeConfig) {
                $attributeDisplayMode = $attributeConfig->getDisplayMode();

                $result = $attributeDisplayMode == 'slider'
                    || $attributeDisplayMode == 'from-to'
                    || $attributeDisplayMode == 'slider+from-to';
            }
        }

        $this->displayModeSliderCache[$attributeCode] = $result;

        return $result;
    }

    public function getAliasGenerationMode(): string
    {
        $aliasGenerationMode = (string)$this->scopeConfig->getValue('mst_seo_filter/general/store_view_alias', ScopeInterface::SCOPE_STORE);

        return $aliasGenerationMode ? $aliasGenerationMode : self::REWRITES_PER_STORE;
    }

    public function isAttributeEnabled(string $attributeCode): bool
    {
        if (isset($this->attributesConfig[$attributeCode])) {
            $attributeConfig = $this->attributesConfig[$attributeCode];
        } else {
            $attributeConfig = ObjectManager::getInstance()->get('Mirasvit\SeoFilter\Service\RewriteService')->getAttributeConfig($attributeCode, false);
            $this->attributesConfig[$attributeCode] = $attributeConfig;
        }
        
        $attributeStatus = null;
        if ($attributeConfig) {
            $attributeStatus = $attributeConfig->getAttributeStatus(); 
        }

        return $attributeStatus == AttributeConfigInterface::SEO_STATUS_ENABLED 
            || $this->isEnabled() && $attributeStatus !== AttributeConfigInterface::SEO_STATUS_DISABLED;
    }

    public function getBrandsUrlSuffixMode(): string
    {
        return (string)$this->scopeConfig->getValue('brand/brand_page/seo/url_suffix_mode', ScopeInterface::SCOPE_STORE);
    }

    public function getBrandsUrlSuffix(): string
    {
        return (string)$this->scopeConfig->getValue('brand/brand_page/seo/custom_suffix', ScopeInterface::SCOPE_STORE);
    }

    public function getLandingPageUrlSuffix(): string
    {
        if (
            class_exists('Mirasvit\LandingPage\Model\Config\ConfigProvider')
            && $this->moduleManager->isEnabled('Mirasvit_LandingPage')
        ) {
            $landingPageConfig = ObjectManager::getInstance()->create('Mirasvit\LandingPage\Model\Config\ConfigProvider');
            if ($landingPageConfig) {
                return $landingPageConfig->getUrlSuffix();
            }
        }

        return '';
    }

    public function getReservedAliases(): array
    {
        return [
            self::LABEL_SALE_YES,
            self::LABEL_SALE_NO,
            self::LABEL_STOCK_IN,
            self::LABEL_STOCK_OUT,
            self::LABEL_RATING_1,
            self::LABEL_RATING_2,
            self::LABEL_RATING_3,
            self::LABEL_RATING_4,
            self::LABEL_RATING_5,
        ];
    }

    public function getDecimalAttributeCodes(): array
    {
        if ($this->sliderAttributeCodesCache !== null) {
            return $this->sliderAttributeCodesCache;
        }

        $attributeCodes = [];
        if (!class_exists('\Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection')) {
            return $attributeCodes;
        }

        /** @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection $attributeCollection */
        $attributeCollection = ObjectManager::getInstance()
            ->create(\Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection::class)
            ->addFieldToSelect(['attribute_code', 'frontend_input'])
            ->addFieldToFilter('frontend_input', ['in' => ['price', 'decimal']]);

        $attributeCodes = $attributeCollection->getColumnValues('attribute_code');

        $this->sliderAttributeCodesCache = $attributeCodes;

        return $attributeCodes;
    }
}
