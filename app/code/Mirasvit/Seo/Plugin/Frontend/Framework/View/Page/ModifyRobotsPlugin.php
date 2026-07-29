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

namespace Mirasvit\Seo\Plugin\Frontend\Framework\View\Page;

use Magento\Catalog\Model\Layer\Category\FilterableAttributeList;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\Seo\Helper\Data as SeoData;
use Mirasvit\Seo\Model\Config;

class ModifyRobotsPlugin
{
    protected $seoData;

    protected $context;

    protected $objectManager;

    protected $registry;

    protected $filterableAttributeList;

    protected $config;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    public function __construct(
        SeoData $seoData,
        Context $context,
        ObjectManagerInterface $objectManager,
        Registry $registry,
        FilterableAttributeList $filterableAttributeList,
        Config $config
    ) {
        $this->seoData                 = $seoData;
        $this->context                 = $context;
        $this->objectManager           = $objectManager;
        $this->request                 = $context->getRequest();
        $this->registry                = $registry;
        $this->filterableAttributeList = $filterableAttributeList;
        $this->config                  = $config;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getRobots(): ?string
    {
        if ($this->context->getStoreManager()->getStore()->isCurrentlySecure()
            && ($robotsCode = $this->config->getHttpsNoindexPages())) {
            return $this->seoData->getMetaRobotsByCode($robotsCode);
        }

        if ($product = $this->registry->registry('current_product')) {
            if ($robots = $this->seoData->getMetaRobotsByCode((int)$product->getSeoMetaRobots())) {
                return $robots;
            }
        }
        $fullAction = $this->request->getFullActionName();
        foreach ($this->config->getNoindexPages() as $record) {
            //for patterns like filterattribute_(attribute_code), filterattribute_(Nlevel), filterattribute_(Nvalues)
            if (strpos($record['pattern'], 'filterattribute_(') !== false
                && ($fullAction == 'catalog_category_view' || $fullAction == 'brand_brand_view')) {
                if ($this->checkFilterPattern($record['pattern'])) {
                    return $this->seoData->getMetaRobotsByCode((int)$record->getOption());
                }
            }

            if ($this->seoData->checkPattern($fullAction, $record->getPattern())
                || $this->seoData->checkPattern($this->seoData->getBaseUri(), $record['pattern'])
                || $this->seoData->checkPattern($this->request->getUriString(), $record['pattern'])) {
                return $this->seoData->getMetaRobotsByCode((int)$record->getOption());
            }
        }

        return null;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function checkFilterPattern(string $pattern): bool
    {
        if (!$this->filterableAttributeList || !$this->filterableAttributeList->getList()) {
            return false;
        }

        $urlParams = $this->request->getParams();

        $filterableAttributes = array_map(
            function ($attribute) {
                return $attribute['attribute_code'];
            },
            $this->filterableAttributeList->getList()->getData('attribute_code')
        );

        $activeFilters = array_intersect(array_keys($urlParams), $filterableAttributes);

        if (!empty($activeFilters)) {
            if (trim($pattern) == 'filterattribute_(alllevel)') {
                return true;
            }
            $usedFiltersCount = count($activeFilters);
            if (strpos($pattern, 'level)') !== false) {
                preg_match('/filterattribute_\\((\d{1})level/', trim($pattern), $levelNumber);
                if (isset($levelNumber[1])) {
                    if ($levelNumber[1] == $usedFiltersCount) {
                        return true;
                    }
                }
            }

            if (strpos($pattern, 'values)') !== false) {
                $totalValuesCount = $this->countFilterValues($urlParams, $activeFilters);

                if (trim($pattern) == 'filterattribute_(allvalues)') {
                    return $totalValuesCount > 0;
                }

                preg_match('/filterattribute_\\((\d+)values/', trim($pattern), $valuesNumber);
                if (isset($valuesNumber[1]) && (int)$valuesNumber[1] == $totalValuesCount) {
                    return true;
                }
            }

            foreach ($activeFilters as $useFilterVal) {
                if (strpos($pattern, '(' . $useFilterVal . ')') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function countFilterValues(array $urlParams, array $activeFilters): int
    {
        $totalValues = 0;

        foreach ($activeFilters as $filterCode) {
            $filterValue = $urlParams[$filterCode] ?? '';

            if (is_string($filterValue) && $filterValue !== '') {
                $values      = explode(',', $filterValue);
                $totalValues += count($values);
            } elseif (is_array($filterValue)) {
                $totalValues += count($filterValue);
            }
        }

        return $totalValues;
    }

    public function afterGetRobots(\Magento\Framework\View\Page\Config $subject, string $result): string
    {
        if ($this->seoData->isIgnoredActions()) {
            return $result;
        }

        return $this->getRobots() ?: $result;
    }
}
