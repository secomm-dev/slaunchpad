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

namespace Mirasvit\SeoFilter\Plugin\Frontend;

use Magento\Framework\View\Element\AbstractBlock;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Service\FriendlyUrlService;
use Mirasvit\SeoFilter\Service\RewriteService;

class SeoFriendlyGetUrlPlugin
{
    private $config;
    private $friendlyUrlService;
    private $rewriteService;

    public function __construct(
        ConfigProvider $config,
        FriendlyUrlService $friendlyUrlService,
        RewriteService $rewriteService
    ) {
        $this->config = $config;
        $this->friendlyUrlService = $friendlyUrlService;
        $this->rewriteService = $rewriteService;
    }

    /**
     * @param AbstractBlock $subject
     * @param string $result
     * @param string $route
     * @param array $params
     * @return string
     */
    public function afterGetUrl(AbstractBlock $subject, $result, $route = '', $params = [])
    {
        if (!$this->config->isApplicable()) {
            return $result;
        }

        // '_current', '_direct', '_fragment', '_query' are used by magento for special urls
        if (array_intersect(array_keys($params), ['_direct', '_fragment', '_query'])) {
            return $result;
        }

        $shouldBuildFriendlyUrl = $this->shouldBuildFriendlyUrl($subject, $result, $route, $params);

        if (!$shouldBuildFriendlyUrl) {
            return $result;
        }

        return $this->buildFriendlyUrl($subject, $result);
    }

    private function shouldBuildFriendlyUrl(AbstractBlock $subject, string $result, ?string $route, array $params): bool
    {
        $allowedPaths = [
            '/catalog/category/view',
            '/brand/brand/view',
            '/all_products_page/index/index',
            '/landing/landing/view',
        ];

        if (isset($params['_current']) && $params['_current'] && empty($route)) {
            if ($this->isUrlIncorrect($result)) {
                $request = $subject->getRequest();
                $allowedActions = [
                    'catalog_category_view',
                    'brand_brand_view',
                    'all_products_page_index_index',
                    'landing_landing_view',
                ];
                return in_array($request->getFullActionName(), $allowedActions);
            }
            return false;
        }

        foreach ($allowedPaths as $path) {
            if (strpos($result, $path) !== false) {
                return true;
            }
        }

        return false;
    }

    private function buildFriendlyUrl(AbstractBlock $subject, string $result): string
    {
        $baseUrl = $this->friendlyUrlService->getClearUrl();
        if (!$baseUrl) {
            return $result;
        }

        $activeFilters = $this->rewriteService->getActiveFilters();
        if (empty($activeFilters)) {
            return $result;
        }

        $filters = $this->convertFiltersFormat($activeFilters);

        return $this->friendlyUrlService->getUrlWithFilters($baseUrl, $filters);
    }

    // Check if URL contains parameter-based paths (example.com/id/4/activity/8/)
    private function isUrlIncorrect(string $url): bool
    {
        return strpos($url, '/id/') !== false || preg_match('#/[a-z_]+/\d+/#', $url);
    }

    // Convert active filters from: ['attr' => ['val1' => 'val1', 'val2' => 'val2']] to: ['attr' => 'val1,val2']
    private function convertFiltersFormat(array $activeFilters): array
    {
        $filters = [];
        foreach ($activeFilters as $attributeCode => $values) {
            if (is_array($values)) {
                $filters[$attributeCode] = implode(',', array_keys($values));
            } else {
                $filters[$attributeCode] = (string)$values;
            }
        }
        return $filters;
    }
}
