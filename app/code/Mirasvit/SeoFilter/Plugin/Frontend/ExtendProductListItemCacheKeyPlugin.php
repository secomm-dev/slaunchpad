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

use Hyva\Theme\ViewModel\ProductListItem;
use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\AbstractBlock;
use Mirasvit\SeoFilter\Service\FilterParamsService;

/**
 * @see \Hyva\Theme\ViewModel\ProductListItem::getItemCacheKeyInfo()
 * @see \Mirasvit\SeoFilter\Plugin\Frontend\AddActiveFilterParamsPlugin
 */
class ExtendProductListItemCacheKeyPlugin
{
    /** @var FilterParamsService */
    private $filterParamsService;

    public function __construct(FilterParamsService $filterParamsService)
    {
        $this->filterParamsService = $filterParamsService;
    }

    public function afterGetItemCacheKeyInfo(
        ProductListItem $subject,
        array $result,
        Product $product,
        AbstractBlock $block,
        string $viewMode,
        string $templateType
    ): array {
        $filterParams = $this->filterParamsService->getFilterParams();

        if (empty($filterParams)) {
            return $result;
        }

        ksort($filterParams);
        $result[] = sha1((string)json_encode($filterParams));

        return $result;
    }
}