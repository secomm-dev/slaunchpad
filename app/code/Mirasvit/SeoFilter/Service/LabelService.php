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

use Magento\Catalog\Model\CategoryRepository;
use Magento\Framework\Filter\FilterManager;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;
use Mirasvit\SeoFilter\Api\Data\RewriteInterface;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Model\Context;
use Mirasvit\SeoFilter\Repository\RewriteRepository;
use Magento\Eav\Model\Entity\Attribute\Option;

class LabelService
{
    private $filterManager;

    private $rewriteRepository;

    private $configProvider;

    private $context;

    private $urlRewriteCollectionFactory;

    private $urlService;

    private $categoryRepository;

    public function __construct(
        FilterManager $filter,
        RewriteRepository $rewriteRepository,
        UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
        UrlService $urlService,
        ConfigProvider $configProvider,
        CategoryRepository $categoryRepository,
        Context $context
    ) {
        $this->filterManager               = $filter;
        $this->rewriteRepository           = $rewriteRepository;
        $this->urlRewriteCollectionFactory = $urlRewriteCollectionFactory;
        $this->urlService                  = $urlService;
        $this->configProvider              = $configProvider;
        $this->context                     = $context;
        $this->categoryRepository          = $categoryRepository;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function createLabel(string $attributeCode, string $itemValue, ?Option $attributeOption, $attributeRewrite = null): string
    {
        if ($this->context->isDecimalAttribute($attributeCode)) {
            if ($this->configProvider->getUrlFormat() == ConfigProvider::URL_FORMAT_ATTR_OPTIONS) {
                $label = $itemValue;
            } else {
                $attributeLabel = $attributeRewrite ? $attributeRewrite->getRewrite() : $attributeCode;
                $label = str_replace('-', ConfigProvider::SEPARATOR_DECIMAL, $itemValue);
                $label = $attributeLabel . ConfigProvider::SEPARATOR_DECIMAL . $label;
            }
        } else {
            if ($attributeCode == 'category_ids') {
                try {
                    $category = $this->categoryRepository->get($itemValue, $this->context->getStoreId());

                    $itemValue = $this->configProvider->getUrlFormat() == ConfigProvider::URL_FORMAT_ATTR_OPTIONS
                        ? $category->getUrlKey()
                        : 'category ' . $category->getUrlKey();
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $itemValue = $attributeCode . ' ' . $itemValue;
                }
            }

            $excludedSymbols = ['™', '℠', '®', '©', '\'', 'ь'];
            $itemValue = str_replace($excludedSymbols, '', $itemValue);
            
            $label = strtolower($this->filterManager->translitUrl($itemValue));

            if (strlen($itemValue) > 0 && strlen($label) == 0 && $attributeOption) {
                $label = $attributeOption->getOptionId();
            }

            $label = $this->getLabelWithSeparator($label);
        }

        return $label;
    }

    public function uniqueLabel(string $label, ?int $storeId = null, int $suffix = 0, ?string $attributeCode = null): string
    {
        if ($storeId === null) {
            $storeId = $this->context->getStoreId();
        }

        $urlFormatConfig = $this->configProvider->getUrlFormatConfig();
        $separator = $urlFormatConfig->getFormat() === ConfigProvider::URL_FORMAT_SHORT_UNDERSCORE
            ? '-' : '_';

        $newLabel = $suffix ? $label . $separator . $suffix : $label;

        $path = $this->urlService->trimCategorySuffix($this->context->getRequest()->getOriginalPathInfo());

//        $possiblePath = $path . '/' . $newLabel;

        if ($attributeCode === 'price') {
            return $newLabel;
        }

        $isExists = $this->urlRewriteCollectionFactory->create()
            ->addFieldToFilter('entity_type', 'category')
            ->addFieldToFilter('redirect_type', 0)
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('request_path', ['regexp' => '(^|/)' . $newLabel . '(/|%)'])
            ->getSize();

        if ($isExists || in_array($newLabel, $this->configProvider->getReservedAliases())) {
            return $this->uniqueLabel($label, $storeId, $suffix + 1);
        }

        $isExists = $this->rewriteRepository->getCollection()
            ->addFieldToFilter(RewriteInterface::REWRITE, $newLabel)
            ->addFieldToFilter(RewriteInterface::STORE_ID, $storeId);

        if ($attributeCode) {
            $isExists->addFieldToFilter(RewriteInterface::ATTRIBUTE_CODE, ['neq' => $attributeCode]);
        }

        $isExists = $isExists->getSize();

        if ($isExists) {
            return $this->uniqueLabel($label, $storeId, $suffix + 1, $attributeCode);
        }

        return $newLabel;
    }

    private function getLabelWithSeparator(string $label): string
    {
        $label = str_replace('__', '_', $label);

        switch ($this->configProvider->getNameSeparator()) {
            case ConfigProvider::NAME_SEPARATOR_NONE:
                $label = str_replace(ConfigProvider::SEPARATOR_FILTERS, '', $label);
                break;

            case ConfigProvider::NAME_SEPARATOR_DASH:
                $label = str_replace(ConfigProvider::SEPARATOR_FILTERS, '_', $label);
                break;

            case ConfigProvider::NAME_SEPARATOR_HYPHEN:
                $label = str_replace(ConfigProvider::SEPARATOR_FILTERS, '-', $label);
                break;

            case ConfigProvider::NAME_SEPARATOR_CAPITAL:
                $labelExploded = explode(ConfigProvider::SEPARATOR_FILTERS, $label);
                $labelExploded = array_map('ucfirst', $labelExploded);

                $label = implode('', $labelExploded);
                $label = lcfirst($label);
                break;
        }

        return $label;
    }

    public function excludeSpecialCharacters($alias): string
    {
        $alias = str_replace(' ', '-', (string)$alias);
        $alias = (string)preg_replace('/[^A-Za-z0-9\-_]/', '', $alias);
        $alias = trim($alias, '-');
        return trim($alias, '_');
    }
}
