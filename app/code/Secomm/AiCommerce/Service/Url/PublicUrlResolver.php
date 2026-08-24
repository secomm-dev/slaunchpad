<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Url;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Store-scoped public URL resolution from url_rewrite rows
 * (SPEC-TASK-QV3R7T §3.5b / plan rev 2 §2b).
 *
 * Never concatenates base_url + url_key + suffix. Canonical selection follows
 * the LC-30-proven bounded rule: among (entity_type, entity_id, store_id,
 * redirect_type=0) rows the LOWEST url_rewrite_id wins ("oldest rewrite wins",
 * same provenance as Secomm_AiDiscoverability CanonicalPolicy). Redirect
 * history aliases are never used as canonical.
 *
 * canonical_url is only emitted when a rewrite can be proven this way;
 * Mirasvit conditional request-bound canonical rules are NOT reproduced
 * offline (documented limitation). Otherwise canonical_url is null and
 * public_url remains available.
 */
class PublicUrlResolver
{
    /**
     * @param UrlFinderInterface $urlFinder url rewrite finder
     */
    public function __construct(private readonly UrlFinderInterface $urlFinder)
    {
    }

    /**
     * Resolve public + canonical URLs of a product in a store.
     *
     * @param ProductInterface $product product entity
     * @param StoreInterface $store store view scope
     * @return array{public_url: ?string, canonical_url: ?string}
     */
    public function getProductUrls(ProductInterface $product, StoreInterface $store): array
    {
        return $this->resolve('product', (int) $product->getId(), $store);
    }

    /**
     * Resolve public + canonical URLs of a category in a store.
     *
     * @param int $categoryId category entity id
     * @param StoreInterface $store store view scope
     * @return array{public_url: ?string, canonical_url: ?string}
     */
    public function getCategoryUrls(int $categoryId, StoreInterface $store): array
    {
        return $this->resolve('category', $categoryId, $store);
    }

    /**
     * Resolve URLs from non-redirect rewrite rows for one entity in one store.
     *
     * @param string $entityType url_rewrite entity type
     * @param int $entityId entity id
     * @param StoreInterface $store store view scope
     * @return array{public_url: ?string, canonical_url: ?string}
     */
    private function resolve(string $entityType, int $entityId, StoreInterface $store): array
    {
        $rewrites = $this->urlFinder->findAllByData([
            UrlRewrite::ENTITY_TYPE => $entityType,
            UrlRewrite::ENTITY_ID => $entityId,
            UrlRewrite::STORE_ID => (int) $store->getId(),
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);

        if ($rewrites === []) {
            return ['public_url' => null, 'canonical_url' => null];
        }

        usort($rewrites, static function (UrlRewrite $a, UrlRewrite $b): int {
            return (int) $a->getUrlRewriteId() <=> (int) $b->getUrlRewriteId();
        });

        /** @var UrlRewrite $canonical */
        $canonical = $rewrites[0];
        $url = $this->buildUrl((string) $canonical->getRequestPath(), $store);

        return ['public_url' => $url, 'canonical_url' => $url];
    }

    /**
     * Build an absolute URL from an internal rewrite path.
     *
     * @param string $path request path from the rewrite row
     * @param StoreInterface $store store view scope
     * @return string absolute URL
     */
    private function buildUrl(string $path, StoreInterface $store): string
    {
        $path = strtok($path, '?') ?: '';
        $path = preg_replace('#/{2,}#', '/', $path) ?? $path;
        $baseUrl = rtrim((string) $store->getBaseUrl(), '/');

        if ($path === '' || $path === '/') {
            return $baseUrl;
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }
}
