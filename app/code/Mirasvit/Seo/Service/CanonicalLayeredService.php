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


namespace Mirasvit\Seo\Service;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Mirasvit\Seo\Model\Config;

class CanonicalLayeredService
{
    private $config;

    private $layerResolver;

    private $urlBuilder;

    private $moduleManager;

    private $objectManager;

    public function __construct(
        Config $config,
        Resolver $layerResolver,
        UrlInterface $urlBuilder,
        Manager $moduleManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->config        = $config;
        $this->layerResolver = $layerResolver;
        $this->urlBuilder    = $urlBuilder;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getCanonicalUrl($category): string
    {
        $canonicalUrl = $category->getUrl();
        $canonicalUrl = strtok($canonicalUrl, '?') ?: $canonicalUrl;

        $layer = $this->layerResolver->get();

        $appliedFilters = $layer->getState()->getFilters();

        if (!count($appliedFilters) || $this->config->getCanonicalLayeredType() == Config::CANONICAL_LAYERED_NOFILTERS) {
            return $canonicalUrl;
        }

        $filters = [];

        foreach ($appliedFilters as $filter) {
            $filters[$filter->getFilter()->getRequestVar()][] = $filter->getValueString();
        }

        if ($this->config->getCanonicalLayeredType() == Config::CANONICAL_LAYERED_ALL) {
            $canonicalUrl = $this->getCanonicalWithFilters($category, $filters);

            return $canonicalUrl;
        }

        $canonicalConfig = $this->config->getCanonicalLayeredConfig();

        if (!count($canonicalConfig)) {
            return $canonicalUrl;
        }

        foreach ($filters as $code => $options) {
            if (!isset($canonicalConfig[$code])) {
                unset($filters[$code]);

                continue;
            }

            if ($canonicalConfig[$code] == Config::CANONICAL_LAYERED_USAGE_ONE && count($options) > 1) {
                unset($filters[$code]);
            }
        }

        $canonicalUrl = $this->getCanonicalWithFilters($category, $filters);

        return $canonicalUrl;
    }

    private function getCanonicalWithFilters($category, array $filters): string
    {
        $params = [];

        foreach ($filters as $code => $values) {
            $params[$code] = implode(',', $values);
        }

        if ($this->moduleManager->isEnabled('Mirasvit_SeoFilter')) {
            /** @var \Mirasvit\SeoFilter\Service\FriendlyUrlService $urlService */
            $urlService = $this->objectManager->get('\Mirasvit\SeoFilter\Service\FriendlyUrlService');

            /** @var \Mirasvit\SeoFilter\Model\ConfigProvider $seoFilterConfig */
            $seoFilterConfig = $this->objectManager->get('\Mirasvit\SeoFilter\Model\ConfigProvider');

            if ($seoFilterConfig->isEnabled()) {
                $url = $urlService->getUrlWithFilters($category->getUrl(), $params);

                return $url;
            }
        }

        $url = $this->urlBuilder->getUrl(
            $category->getUrlKey(),
            ['_current' => false, '_use_rewrite' => true, '_query' => $params]
        );

        return urldecode($url);
    }
}
