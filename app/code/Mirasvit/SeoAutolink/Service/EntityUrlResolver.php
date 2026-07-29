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

namespace Mirasvit\SeoAutolink\Service;

use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class EntityUrlResolver
{
    private $urlFinder;
    private $storeManager;

    public function __construct(
        UrlFinderInterface    $urlFinder,
        StoreManagerInterface $storeManager
    ) {
        $this->urlFinder    = $urlFinder;
        $this->storeManager = $storeManager;
    }

    public function resolveProductUrl(int $entityId): ?string
    {
        return $this->resolveUrl($entityId, 'product');
    }

    public function resolveCategoryUrl(int $entityId): ?string
    {
        return $this->resolveUrl($entityId, 'category');
    }

    private function resolveUrl(int $entityId, string $entityType): ?string
    {
        $storeId = (int)$this->storeManager->getStore()->getId();

        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::ENTITY_ID     => $entityId,
            UrlRewrite::ENTITY_TYPE   => $entityType,
            UrlRewrite::STORE_ID      => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);

        if (!$rewrite) {
            return null;
        }

        return $this->storeManager->getStore()->getBaseUrl() . $rewrite->getRequestPath();
    }
}
