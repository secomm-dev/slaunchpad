<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Url;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Store-scoped public URL resolution from url_rewrite rows
 * (SPEC-TASK-QV3R7T §3.5b / plan rev 2 §2b, P1.2 batch loader).
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
 *
 * List endpoints (search, category tree) MUST use resolveMany(): ONE bounded
 * SELECT per entity type/store for the whole page — never a per-entity
 * UrlFinder loop (P1.2). Single-entity getProductUrls()/getCategoryUrls()
 * delegate to the same code path so batch and single resolution cannot drift.
 */
class PublicUrlResolver
{
    private const TABLE = 'url_rewrite';

    /**
     * @param ResourceConnection $resource db resource connection
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Resolve public + canonical URLs of a product in a store (single bounded lookup).
     *
     * @param ProductInterface $product product entity
     * @param StoreInterface $store store view scope
     * @return array{public_url: ?string, canonical_url: ?string}
     */
    public function getProductUrls(ProductInterface $product, StoreInterface $store): array
    {
        return $this->resolveOne('product', (int) $product->getId(), $store);
    }

    /**
     * Resolve public + canonical URLs of a category in a store (single bounded lookup).
     *
     * @param int $categoryId category entity id
     * @param StoreInterface $store store view scope
     * @return array{public_url: ?string, canonical_url: ?string}
     */
    public function getCategoryUrls(int $categoryId, StoreInterface $store): array
    {
        return $this->resolveOne('category', $categoryId, $store);
    }

    /**
     * Batch-resolve URLs for many entities of one type in one store.
     *
     * ONE bounded SELECT (entity_type, entity_id IN (...), store_id,
     * redirect_type=0, explicit column list) — rows are grouped in memory and
     * the lowest url_rewrite_id per entity wins, exactly like the single path.
     *
     * @param string $entityType url_rewrite entity type (product|category)
     * @param int[] $entityIds entity ids
     * @param StoreInterface $store store view scope
     * @return array<int, array{public_url: ?string, canonical_url: ?string}> entity id => urls
     */
    public function resolveMany(string $entityType, array $entityIds, StoreInterface $store): array
    {
        $ids = array_values(array_unique(array_map('intval', $entityIds)));
        $result = [];

        foreach ($ids as $id) {
            $result[$id] = ['public_url' => null, 'canonical_url' => null];
        }

        if ($ids === []) {
            return $result;
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            $this->resource->getTableName(self::TABLE),
            ['url_rewrite_id', 'entity_id', 'request_path', 'target_path', 'redirect_type', 'store_id']
        );
        $select->where('entity_type = ?', $entityType);
        $select->where('entity_id IN (?)', $ids);
        $select->where('store_id = ?', (int) $store->getId());
        $select->where('redirect_type = ?', 0);

        $oldest = [];

        foreach ($connection->fetchAll($select) as $row) {
            $entityId = (int) $row['entity_id'];
            $rewriteId = (int) $row['url_rewrite_id'];

            if (!isset($oldest[$entityId]) || $rewriteId < $oldest[$entityId]['url_rewrite_id']) {
                $oldest[$entityId] = ['url_rewrite_id' => $rewriteId, 'request_path' => (string) $row['request_path']];
            }
        }

        foreach ($oldest as $entityId => $winner) {
            $url = $this->buildUrl($winner['request_path'], $store);
            $result[$entityId] = ['public_url' => $url, 'canonical_url' => $url];
        }

        return $result;
    }

    /**
     * Resolve URLs for one entity via the shared batch path (no drift).
     *
     * @param string $entityType url_rewrite entity type
     * @param int $entityId entity id
     * @param StoreInterface $store store view scope
     * @return array{public_url: ?string, canonical_url: ?string}
     */
    private function resolveOne(string $entityType, int $entityId, StoreInterface $store): array
    {
        return $this->resolveMany($entityType, [$entityId], $store)[$entityId]
            ?? ['public_url' => null, 'canonical_url' => null];
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
