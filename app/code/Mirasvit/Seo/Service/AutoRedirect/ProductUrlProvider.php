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



namespace Mirasvit\Seo\Service\AutoRedirect;

use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Resolves the storefront request path(s) of a product, per store view.
 *
 * Covers both URL shapes the auto-redirect must catch:
 *  - SEO-friendly rewrites (e.g. "blue-widget.html") read from url_rewrite;
 *  - the direct "catalog/product/view/id/X" form (always derivable from the id).
 */
class ProductUrlProvider
{
    /**
     * @var UrlFinderInterface
     */
    private $urlFinder;

    public function __construct(
        UrlFinderInterface $urlFinder
    ) {
        $this->urlFinder = $urlFinder;
    }

    /**
     * Request paths (relative, no leading slash) for a product on a given store view.
     * Returns the SEO-friendly rewrite paths plus the direct catalog URL.
     *
     * @return string[]
     */
    public function getRequestPaths(int $productId, int $storeId): array
    {
        $paths = [];

        $rewrites = $this->urlFinder->findAllByData([
            UrlRewrite::ENTITY_ID     => $productId,
            UrlRewrite::ENTITY_TYPE   => 'product',
            UrlRewrite::STORE_ID      => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);

        foreach ($rewrites as $rewrite) {
            $requestPath = trim((string)$rewrite->getRequestPath());
            if ($requestPath !== '') {
                $paths[$requestPath] = $requestPath;
            }
        }

        // Direct product URL is always valid even without a rewrite row.
        $direct          = 'catalog/product/view/id/' . $productId;
        $paths[$direct]  = $direct;

        return array_values($paths);
    }
}
