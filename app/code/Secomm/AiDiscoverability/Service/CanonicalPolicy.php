<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service;

use Magento\Store\Api\Data\StoreInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Bounded storefront-canonical policy (SPEC-TASK-0X552E §3.1).
 *
 * Reproduces the PROVEN parts of the active storefront canonical behavior:
 * - Category: request path from the url_rewrite row with the LOWEST url_rewrite_id
 *   among (entity_type=category, entity_id, store_id, redirect_type=0) — exactly
 *   Mirasvit\Seo\Observer\Canonical::getCategoryRewrite() ("oldest rewrite wins").
 * - CMS/priority: self-canonical path used as-is against the store base URL.
 * - Query strings stripped; trailing-slash policy delegated to SeoPolicy.
 *
 * UrlFinderInterface is NOT treated as absolute canonical truth — known
 * unsupported cases (conditional Mirasvit canonical_rewrite rules, cross-domain
 * product canonicals) are documented in the module README.
 */
class CanonicalPolicy
{
    /**
     * @param UrlFinderInterface $urlFinder url rewrite finder
     * @param SeoPolicy $seoPolicy trailing-slash policy adapter
     */
    public function __construct(
        private readonly UrlFinderInterface $urlFinder,
        private readonly SeoPolicy $seoPolicy
    ) {
    }

    /**
     * Absolute canonical category URL for a store, null when not public.
     *
     * @param int $categoryId category entity id
     * @param StoreInterface $store store view scope
     * @return string|null canonical URL or null when the category has no non-redirect rewrite
     */
    public function getCategoryUrl(int $categoryId, StoreInterface $store): ?string
    {
        $rewrites = $this->urlFinder->findAllByData([
            UrlRewrite::ENTITY_TYPE => 'category',
            UrlRewrite::ENTITY_ID => $categoryId,
            UrlRewrite::STORE_ID => (int) $store->getId(),
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);

        if ($rewrites === []) {
            return null;
        }

        usort($rewrites, static function (UrlRewrite $a, UrlRewrite $b): int {
            return (int) $a->getUrlRewriteId() <=> (int) $b->getUrlRewriteId();
        });

        /** @var UrlRewrite $canonical */
        $canonical = $rewrites[0];

        return $this->buildUrl($canonical->getRequestPath(), $store);
    }

    /**
     * Absolute canonical URL for a bare internal path (CMS identifier, merchant priority path).
     *
     * @param string $path internal path
     * @param StoreInterface $store store view scope
     * @return string absolute URL
     */
    public function getUrlForPath(string $path, StoreInterface $store): string
    {
        return $this->buildUrl($path, $store);
    }

    /**
     * Public home page URL of the store.
     *
     * @param StoreInterface $store store view scope
     * @return string home page URL
     */
    public function getHomeUrl(StoreInterface $store): string
    {
        return rtrim((string) $store->getBaseUrl(), '/');
    }

    /**
     * Build an absolute URL from an internal path for a store.
     *
     * @param string $path internal path
     * @param StoreInterface $store store view scope
     * @return string absolute URL
     */
    private function buildUrl(string $path, StoreInterface $store): string
    {
        $path = strtok($path, '?') ?: '';
        $path = $this->collapseSlashes($path);
        $path = $this->seoPolicy->applyTrailingSlash($path, (int) $store->getId());

        $baseUrl = rtrim((string) $store->getBaseUrl(), '/');

        if ($path === '' || $path === '/') {
            return $baseUrl;
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * Collapse consecutive slashes in a path to a single slash.
     *
     * @param string $path internal path
     * @return string collapsed path
     */
    private function collapseSlashes(string $path): string
    {
        return preg_replace('#/{2,}#', '/', $path) ?? $path;
    }
}
