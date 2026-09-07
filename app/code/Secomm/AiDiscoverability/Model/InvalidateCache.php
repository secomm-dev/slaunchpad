<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Model;

use Secomm\AiDiscoverability\Model\Cache\Type;
use Secomm\AiDiscoverability\Service\LlmsTxtProvider;

/**
 * Targeted tag-based invalidation helper. Observers only call clean*() here —
 * no regeneration happens in observers (lazy regen on next request).
 *
 * IMPORTANT — clean() contract differs from App\Cache\Proxy (see
 * SPEC-CHANGE-AIDL-CINV1): this class holds the module cache TYPE
 * (Zend Cache FrontendInterface via TagScope), whose native signature is
 * clean($mode, array $tags) / clean(CLEANING_MODE_ALL). The Proxy's
 * clean(array $tags) one-argument contract does NOT apply here.
 */
class InvalidateCache
{
    /**
     * @param Type $cacheType module cache type frontend (tag-scoped)
     * @param LlmsTxtProvider $provider cache id/tag authority
     */
    public function __construct(
        private readonly Type $cacheType,
        private readonly LlmsTxtProvider $provider
    ) {
    }

    /**
     * Invalidate a single store's cached llms.txt.
     *
     * TagScope rewrites MATCHING_TAG to [store tag, type tag] — both are on
     * every entry, so exactly that store's entries are removed.
     *
     * @param int $storeId store view id
     * @return void
     */
    public function cleanStore(int $storeId): void
    {
        $this->cacheType->clean(
            \Zend_Cache::CLEANING_MODE_MATCHING_TAG,
            [$this->provider->storeTag($storeId)]
        );
    }

    /**
     * Invalidate the cached llms.txt of each given store.
     *
     * @param int[] $storeIds store view ids
     * @return void
     */
    public function cleanStores(array $storeIds): void
    {
        foreach (array_unique(array_map('intval', $storeIds)) as $storeId) {
            $this->cleanStore($storeId);
        }
    }

    /**
     * Invalidate every store (only for scope-unspecific changes).
     *
     * TagScope rewrites CLEANING_MODE_ALL to MATCHING_TAG [type tag] — never
     * a flush of the shared default frontend.
     *
     * @return void
     */
    public function cleanAll(): void
    {
        $this->cacheType->clean();
    }
}
