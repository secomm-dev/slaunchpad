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

use Magento\Swatches\Block\Product\Renderer\Listing\Configurable;
use Mirasvit\SeoFilter\Service\FilterParamsService;

/**
 * Fixes swatch block cache key for SEO-friendly URLs.
 *
 * @see \Magento\Swatches\Block\Product\Renderer\Listing\Configurable::getCacheKey()
 */
class FixSwatchCacheKeyPlugin
{
    private $filterParamsService;

    public function __construct(FilterParamsService $filterParamsService)
    {
        $this->filterParamsService = $filterParamsService;
    }

    public function afterGetCacheKey(Configurable $subject, string $result): string
    {
        $filterParams = $this->filterParamsService->getFilterParams();

        if (empty($filterParams)) {
            return $result;
        }

        $productId = '';
        $product = $subject->getProduct();
        if ($product && $product->getId()) {
            $productId = '-p' . $product->getId();
        }

        ksort($filterParams);
        $paramsHash = sha1((string)json_encode($filterParams));

        return $result . '-seofilter-' . $paramsHash . $productId;
    }
}
