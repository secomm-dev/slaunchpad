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

namespace Mirasvit\SeoFilter\Service;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\Product\Attribute\Source\Countryofmanufacture;
use Magento\Eav\Model\Entity\Attribute\Option as AttributeOption;
use Magento\Framework\Module\Manager as ModuleManager;
use Mirasvit\Core\Service\CompatibilityService;
use Mirasvit\SeoFilter\Api\Data\AttributeConfigInterface;
use Mirasvit\SeoFilter\Api\Data\RewriteInterface;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Model\Context;
use Mirasvit\SeoFilter\Repository\RewriteRepository;
use Mirasvit\SeoFilter\Model\RewriteFactory;
use Mirasvit\SeoFilter\Repository\AttributeConfigRepository;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RewriteService
{
    /** @var array */
    private static $activeFilters = null;

    private static $aliasLookupCache = [];

    private static $attributePreloaded = [];

    private        $rewriteRepository;

    private        $layerResolver;

    private        $context;

    private        $labelService;

    private        $configProvider;

    private        $moduleManager;

    private        $cacheService;

    private        $rewriteFactory;

    private        $countryOfManufactureSource;

    private        $attributeConfigRepository;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        RewriteRepository $rewriteRepository,
        LayerResolver $layerResolver,
        Context $context,
        LabelService $labelService,
        ConfigProvider $configProvider,
        ModuleManager $moduleManager,
        RewriteFactory $rewriteFactory,
        CacheService $cacheService,
        AttributeConfigRepository $attributeConfigRepository,
        Countryofmanufacture $countryOfManufactureSource
    ) {
        $this->rewriteRepository = $rewriteRepository;
        $this->layerResolver     = $layerResolver;
        $this->context           = $context;
        $this->labelService      = $labelService;
        $this->configProvider    = $configProvider;
        $this->moduleManager     = $moduleManager;
        $this->rewriteFactory    = $rewriteFactory;
        $this->cacheService      = $cacheService;

        $this->attributeConfigRepository        = $attributeConfigRepository;
        $this->countryOfManufactureSource = $countryOfManufactureSource;
    }

    public function getAttributeRewrite(string $attributeCode, ?int $storeId = null, bool $useCache = true): ?RewriteInterface
    {
        if ($storeId === null) {
            $storeId = $this->context->getStoreId();
        }

        if ($useCache && $rewriteData = $this->cacheService->getCache('getAttributeRewrite', [$attributeCode, $storeId])) {
            $rewrite = $this->rewriteFactory->create();
            $rewrite->setData($rewriteData);

            return $rewrite;
        }

        /** @var RewriteInterface $rewrite */
        $rewrite = $this->rewriteRepository->getCollection()
            ->addFieldToFilter(RewriteInterface::ATTRIBUTE_CODE, $attributeCode)
            ->addFieldToFilter(RewriteInterface::OPTION, ['null' => true])
            ->addFieldToFilter(RewriteInterface::STORE_ID, $storeId)
            ->getFirstItem();

        if ($rewrite->getId()) {
            $this->cacheService->setCache('getAttributeRewrite', [$attributeCode, $storeId], [$rewrite->getData()], CacheService::REWRITE_CACHE_TTL);

            return $rewrite;
        }

        $rewrite = $this->createNewAttributeRewrite($attributeCode, $storeId);

        return $rewrite ? $rewrite : null;
    }

    public function getAttributeRewriteByAlias(string $alias, ?int $storeId = null, bool $useCache = true): ?RewriteInterface
    {
        if ($storeId === null) {
            $storeId = $this->context->getStoreId();
        }

        if ($useCache && $rewriteData = $this->cacheService->getCache('getAttributeRewriteByAlias', [$alias, $storeId])) {
            $rewrite = $this->rewriteFactory->create();
            $rewrite->setData($rewriteData);

            return $rewrite;
        }
        /** @var RewriteInterface $rewrite */
        $rewrite = $this->rewriteRepository->getCollection()
            ->addFieldToFilter(RewriteInterface::REWRITE, $alias)
            ->addFieldToFilter(RewriteInterface::OPTION, ['null' => true])
            ->addFieldToFilter(RewriteInterface::STORE_ID, $storeId)
            ->getFirstItem();

        if ($rewrite->getId()) {
            $this->cacheService->setCache('getAttributeRewriteByAlias', [$alias, $storeId], [$rewrite->getData()], CacheService::REWRITE_CACHE_TTL);

            return $rewrite;
        }

        return null;
    }


    /**
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getOptionRewritesBatch(array $aliases, int $storeId, ?string $attributeCode = null): array
    {
        if (empty($aliases)) {
            return [];
        }

        $map          = [];
        $cacheMisses = [];

        foreach ($aliases as $alias) {
            $staticKey = $this->aliasStaticKey($storeId, $alias);
            if (array_key_exists($staticKey, self::$aliasLookupCache)) {
                if (self::$aliasLookupCache[$staticKey]) {
                    $map[$alias] = true;
                }
            } else {
                $cacheMisses[] = $alias;
            }
        }

        if (empty($cacheMisses)) {
            return $map;
        }

        $dbMisses = [];
        foreach ($cacheMisses as $alias) {
            $cached = $this->cacheService->getCache('batchOptionAlias', [$storeId, $alias]);
            if ($cached !== null) {
                $this->recordAlias($storeId, $alias, (bool)($cached[0] ?? false), $map);
            } else {
                $dbMisses[] = $alias;
            }
        }

        if (empty($dbMisses)) {
            return $map;
        }

        $collection = $this->rewriteRepository->getCollection()
            ->addFieldToFilter(RewriteInterface::REWRITE, ['in' => $dbMisses])
            ->addFieldToFilter(RewriteInterface::OPTION, ['notnull' => true])
            ->addFieldToFilter(RewriteInterface::STORE_ID, $storeId);

        if ($attributeCode) {
            $collection->addFieldToFilter(RewriteInterface::ATTRIBUTE_CODE, $attributeCode);
        }

        $found = [];
        foreach ($collection as $rewrite) {
            $found[$rewrite->getRewrite()] = true;
        }

        foreach ($dbMisses as $alias) {
            $exists = isset($found[$alias]);
            if ($exists) {
                $this->cacheService->setCache('batchOptionAlias', [$storeId, $alias], [$exists], CacheService::REWRITE_CACHE_TTL);
            }
            $this->recordAlias($storeId, $alias, $exists, $map);
        }

        return $map;
    }

    public function getOptionRewrite(string $attributeCode, string $filterValue, ?int $storeId = null, bool $useCache = true): ?RewriteInterface
    {
        $filterValue = $this->sanitizeOption(trim($filterValue));

        if ($attributeCode == ConfigProvider::FILTER_RATING) {
            return $this->getRatingFilterRewrite((int)$filterValue);
        } elseif ($attributeCode == ConfigProvider::FILTER_STOCK) {
            return $this->getStockFilterRewrite((int)$filterValue);
        } elseif ($attributeCode == ConfigProvider::FILTER_SALE) {
            return $this->getSaleFilterRewrite((int)$filterValue);
        } elseif ($attributeCode == ConfigProvider::FILTER_NEW) {
            return $this->getNewFilterRewrite((int)$filterValue);
        }

        if ($storeId === null) {
            $storeId = $this->context->getStoreId();
        }

        if ($useCache) {
            $this->preloadOptionRewritesByAttribute($attributeCode, $storeId);

            if ($rewriteData = $this->cacheService->getCache('getOptionRewrite', [$attributeCode, $filterValue, $storeId])) {
                $rewrite = $this->rewriteFactory->create();
                $rewrite->setData($rewriteData);

                return $rewrite;
            }
        }

        /** @var RewriteInterface $rewrite */
        $rewrite = $this->rewriteRepository->getCollection()
            ->addFieldToFilter(RewriteInterface::ATTRIBUTE_CODE, $attributeCode)
            ->addFieldToFilter(RewriteInterface::OPTION, $filterValue)
            ->addFieldToFilter(RewriteInterface::STORE_ID, $storeId)
            ->getFirstItem();

        if ($rewrite->getId()) {
            if (!$this->configProvider->isDisplayModeSlider($rewrite->getAttributeCode())) {
                $this->cacheService->setCache('getOptionRewrite', [$attributeCode, $filterValue, $storeId], [$rewrite->getData()], CacheService::REWRITE_CACHE_TTL);
            }
            return $rewrite;
        }

        $rewrite = $this->createNewOptionRewrite($attributeCode, $filterValue, $storeId);

        return $rewrite ? $rewrite : null;
    }

    private function preloadOptionRewritesByAttribute(string $attributeCode, int $storeId): void
    {
        $key = $attributeCode . '_' . $storeId;
        if (isset(self::$attributePreloaded[$key])) {
            return;
        }
        self::$attributePreloaded[$key] = true;

        if ($this->cacheService->getCache('optRewritePreloaded', [$attributeCode, $storeId]) !== null) {
            return;
        }

        $this->cacheService->setCache('optRewritePreloaded', [$attributeCode, $storeId], [1], CacheService::REWRITE_CACHE_TTL);

        $collection = $this->rewriteRepository->getCollection()
            ->addFieldToFilter(RewriteInterface::ATTRIBUTE_CODE, $attributeCode)
            ->addFieldToFilter(RewriteInterface::OPTION, ['notnull' => true])
            ->addFieldToFilter(RewriteInterface::STORE_ID, $storeId);

        foreach ($collection as $rewrite) {
            $this->cacheService->setCache(
                'getOptionRewrite',
                [$attributeCode, $rewrite->getOption(), $storeId],
                [$rewrite->getData()],
                CacheService::REWRITE_CACHE_TTL
            );
        }
    }

    public function getActiveFilters(): array
    {
        if (self::$activeFilters === null) {
            self::$activeFilters = [];

            $layer = $this->layerResolver->get();
            foreach ($layer->getState()->getFilters() as $item) {
                $filter = $item->getFilter();

                if (is_array($item->getData('value'))) {
                    $filterValue = implode(ConfigProvider::SEPARATOR_FILTER_VALUES, $item->getData('value'));
                } else {
                    $filterValue = (string)$item->getData('value');
                }

                if ($filter->getData('attribute_model')) {
                    $attributeCode = $filter->getAttributeModel()->getAttributeCode();
                } else {
                    $attributeCode = $filter->getRequestVar();
                }

                $filterValue = explode(ConfigProvider::SEPARATOR_FILTER_VALUES, $filterValue);
                if ($attributeCode === 'price') {
                    $filterValue = [implode('-', $filterValue)];
                }

                foreach ($filterValue as $value) {
                    self::$activeFilters[$attributeCode][$value] = $value;
                }
            }
        }

        return self::$activeFilters;
    }

    private function getStockFilterRewrite(int $stockValue): RewriteInterface
    {
        $rewrite = $stockValue === 2 ? ConfigProvider::LABEL_STOCK_IN : ConfigProvider::LABEL_STOCK_OUT;

        return $this->makeStaticRewrite($rewrite);
    }

    private function getSaleFilterRewrite(int $value): RewriteInterface
    {
        $rewrite = $value ? ConfigProvider::LABEL_SALE_YES : ConfigProvider::LABEL_SALE_NO;

        return $this->makeStaticRewrite($rewrite);
    }

    private function getNewFilterRewrite(int $value): RewriteInterface
    {
        $rewrite = ConfigProvider::FILTER_NEW . '_';

        $rewrite .= $value ? 'yes' : 'no';

        return $this->makeStaticRewrite($rewrite);
    }

    private function getRatingFilterRewrite(int $ratingValue): RewriteInterface
    {
        switch ($ratingValue) {
            case 1:
                $rewrite = ConfigProvider::LABEL_RATING_1;
                break;
            case 2:
                $rewrite = ConfigProvider::LABEL_RATING_2;
                break;
            case 3:
                $rewrite = ConfigProvider::LABEL_RATING_3;
                break;
            case 4:
                $rewrite = ConfigProvider::LABEL_RATING_4;
                break;
            case 5:
                $rewrite = ConfigProvider::LABEL_RATING_5;
                break;
            default:
                $rewrite = ConfigProvider::LABEL_RATING_5;
        }

        return $this->makeStaticRewrite($rewrite);
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function createNewOptionRewrite(string $attributeCode, string $filterValue, ?int $storeId = null): ?RewriteInterface
    {
        $attribute = $this->context->getAttribute($attributeCode);

        if (!$attribute) {
            return null;
        }

        $attributeId = (int)$attribute->getId();

        $attributeOption = $this->context->getAttributeOption($attributeId, (int)$filterValue, (int)$storeId);

        if ($groupedLabel = $this->getGroupedOptionLabel($filterValue, (int)$storeId)) {
            $label = $this->labelService->createLabel($attributeCode, $groupedLabel, $attributeOption);
        } elseif ($this->context->isDecimalAttribute($attributeCode)) {
            $attributeRewrite = $this->getAttributeRewrite($attributeCode);
            $label = $this->labelService->createLabel($attributeCode, $filterValue, $attributeOption, $attributeRewrite);
        } elseif ($attributeCode == 'country_of_manufacture') {
            $allCountries = $this->countryOfManufactureSource->getAllOptions();

            $label = '';

            foreach ($allCountries as $country) {
                if ($country['value'] == $filterValue) {
                    $label = $this->labelService->createLabel($attributeCode, $country['label'], $attributeOption);
                    break;
                }
            }

            if (!$label) {
                $label = $this->labelService->createLabel(
                    $attributeCode,
                    $attributeCode . ' ' . $filterValue,
                    $attributeOption)
                ;
            }
        } elseif (
            $attributeOption
            && ($optionValue = $this->resolveAttributeOptionValue($attributeOption))
        ) {
            $label = $this->labelService->createLabel($attributeCode, $optionValue, $attributeOption);
        } elseif ((int)$filterValue === 1 || $filterValue === '1') {
            $label = $attributeCode;
        } elseif ((int)$filterValue === 0 || $filterValue === '0') {
            $label = $attributeCode . '_no';
        } elseif ($attributeCode == 'category_ids') {
            $label = $this->labelService->createLabel($attributeCode, $filterValue, $attributeOption);
        } else {
            $label = $this->labelService->createLabel($attributeCode, $attributeCode . ' ' . $filterValue, $attributeOption);
        }

        if ($storeId === null) {
            $storeId = $this->context->getStoreId();
        }

        if ($this->configProvider->getUrlFormat() === ConfigProvider::URL_FORMAT_OPTIONS) {
            $priceAttributeCode = $attributeCode === 'price' ? 'price' : null;
            $label = $this->labelService->uniqueLabel($label, $storeId, 0, $priceAttributeCode);
        }

        $filterValue = trim($filterValue);
        $filterValue = $this->sanitizeOption($filterValue);

        $rewrite = $this->rewriteRepository->create();
        $rewrite->setAttributeCode($attributeCode)
            ->setOption($filterValue)
            ->setRewrite($label)
            ->setStoreId($storeId);

        if (!$this->configProvider->isDisplayModeSlider($attributeCode)) {
            $this->rewriteRepository->save($rewrite);
            $newAlias = $rewrite->getRewrite();
            self::$aliasLookupCache[$this->aliasStaticKey($storeId, $newAlias)] = true;
            $this->cacheService->clearCache('batchOptionAlias', [$storeId, $newAlias]);
            $this->cacheService->clearCache('optRewritePreloaded', [$attributeCode, $storeId]);
            unset(self::$attributePreloaded[$attributeCode . '_' . $storeId]);
        }

        return $rewrite;
    }

    private function getGroupedOptionLabel(string $optionValue, int $storeId): ?string
    {
        if (!$this->moduleManager->isEnabled('Mirasvit_LayeredNavigation')) {
            return null;
        }

        try {
            /** @var \Mirasvit\LayeredNavigation\Repository\GroupRepository $groupRepository */
            $groupRepository = CompatibilityService::getObjectManager()
                ->get('\Mirasvit\LayeredNavigation\Repository\GroupRepository');
        } catch (\Exception $e) {
            return null;
        }

        /** @var \Mirasvit\LayeredNavigation\Api\Data\GroupInterface|null $group */
        $group = $groupRepository->getByCode($optionValue);
        if ($group) {
            return $group->getLabelByStoreId($storeId);
        }

        return null;
    }

    private function createNewAttributeRewrite(string $attributeCode, ?int $storeId = null): ?RewriteInterface
    {
        $attribute = $this->context->getAttribute($attributeCode);

        if (!$attribute && !in_array($attributeCode, [
            ConfigProvider::FILTER_STOCK,
            ConfigProvider::FILTER_SALE,
            ConfigProvider::FILTER_NEW,
            ConfigProvider::FILTER_RATING
        ])) {
            return null;
        }

        if ($storeId === null) {
            $storeId = $this->context->getStoreId();
        }

        $urlRewrite = $this->labelService->uniqueLabel($attributeCode == 'category_ids' ? 'category' : $attributeCode, $storeId);

        $rewrite = $this->rewriteRepository->create();
        $rewrite->setAttributeCode($attributeCode)
            ->setRewrite($urlRewrite)
            ->setStoreId($storeId);

        $this->rewriteRepository->save($rewrite);

        return $rewrite;
    }

    private function makeStaticRewrite(string $value): RewriteInterface
    {
        $rewrite = $this->rewriteRepository->create();
        $rewrite->setRewrite($value);

        return $rewrite;
    }

    private function resolveAttributeOptionValue(AttributeOption $attributeOption): ?string
    {
        $value = trim($attributeOption->getValue());

        if (!$value) {
            $value = trim((string)$attributeOption->getData('store_default_value'));
        }

        if (!$value) {
            $value = trim((string)$attributeOption->getData('default_value'));
        }

        return $value ?: null;
    }

    public function getAttributeConfig(string $attributeCode, bool $createIfNotExist = true, bool $useCache = true): ?AttributeConfigInterface
    {
        if ($useCache && $attributeConfigData = $this->cacheService->getCache('getAttributeConfig', [$attributeCode, 1])) {
            $attributeConfig = $this->attributeConfigRepository->create();
            $attributeConfig->setData($attributeConfigData);
            return $attributeConfig;
        }

        $attributeConfig = $this->attributeConfigRepository->getCollection()
            ->addFieldToFilter(AttributeConfigInterface::ATTRIBUTE_CODE, $attributeCode)
            ->getFirstItem();
        
        if ($attributeConfig->getId()) {
            $this->cacheService->setCache('getAttributeConfig', [$attributeCode, 1], [$attributeConfig->getData()], CacheService::REWRITE_CACHE_TTL);
            return $attributeConfig;
        }

        if (!$createIfNotExist) {
            $this->cacheService->setCache('getAttributeConfig', [$attributeCode, 1], [[
                AttributeConfigInterface::ATTRIBUTE_CODE => $attributeCode,
                AttributeConfigInterface::ATTRIBUTE_STATUS => null
            ]], CacheService::REWRITE_CACHE_TTL);
            return null;
        }

        $attributeConfig = $this->createNewAttributeConfig($attributeCode);

        return $attributeConfig ? $attributeConfig : null;
    }

    private function createNewAttributeConfig(string $attributeCode): ?AttributeConfigInterface
    {
        $attribute = $this->context->getAttribute($attributeCode);

        if (!$attribute && !in_array($attributeCode, [
            ConfigProvider::FILTER_STOCK,
            ConfigProvider::FILTER_SALE,
            ConfigProvider::FILTER_NEW,
            ConfigProvider::FILTER_RATING
        ])) {
            return null;
        }

        $attributeConfig = $this->attributeConfigRepository->create();
        $attributeConfig->setAttributeCode($attributeCode);

        $this->attributeConfigRepository->save($attributeConfig);

        return $attributeConfig;
    }

    public function enabledAttrubteExists(): bool
    {
        $enabledAttribute = $this->attributeConfigRepository->getCollection()
            ->addFieldToFilter(AttributeConfigInterface::ATTRIBUTE_STATUS, AttributeConfigInterface::SEO_STATUS_ENABLED)
            ->getSize();

        return boolval($enabledAttribute);
    }

    private function aliasStaticKey(int $storeId, string $alias): string
    {
        return $storeId . '|' . $alias;
    }

    private function recordAlias(int $storeId, string $alias, bool $exists, array &$map): void
    {
        self::$aliasLookupCache[$this->aliasStaticKey($storeId, $alias)] = $exists;
        if ($exists) {
            $map[$alias] = true;
        }
    }

    protected function sanitizeOption(string $option): string
    {
        // Remove dangerous characters: ', ", ;, #, (, ), =, <, >, \, `, --, /*, */, and whitespace
        return (string)preg_replace('/[\'";#()=<>\\\`]|--|\/\*|\*\/|\s+/u', '', $option);
    }

}
