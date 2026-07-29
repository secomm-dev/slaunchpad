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

namespace Mirasvit\SeoAudit\Service\Resolver;

use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Api\UrlEntityResolverInterface;

class CatalogUrlRewriteResolver implements UrlEntityResolverInterface
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

    public function resolve(UrlInterface $url): ?array
    {
        $requestPath = ltrim((string)parse_url($url->getUrl(), PHP_URL_PATH), '/');

        if (!$requestPath) {
            return null;
        }

        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::REQUEST_PATH => $requestPath,
            UrlRewrite::STORE_ID     => (int)$this->storeManager->getStore()->getId(),
        ]);

        if (!$rewrite) {
            return null;
        }

        $type = $rewrite->getEntityType();

        if ($type === 'cms-page') {
            $type = 'cms_page';
        }

        return [
            'type' => $type,
            'id'   => (int)$rewrite->getEntityId(),
        ];
    }
}
