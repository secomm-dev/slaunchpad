<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * System > Cache Management cache type "AI Discoverability (llms.txt)".
 *
 * Canonical core pattern (e.g. Magento\Integration\Model\Cache\Type): a
 * TagScope-decorated frontend bound to the cache type identifier, so
 * `cache:status` exposes the type, `cache:clean secomm_ai_discoverability`
 * removes exactly the llms.txt entries (type tag scoping), and disabling the
 * type stops caching entirely (FrontendPool AccessProxy gating — loads miss
 * and saves are skipped, equivalent to lifetime 0).
 */
class Type extends TagScope
{
    /**
     * Cache type code unique among all cache types.
     */
    public const TYPE_IDENTIFIER = 'secomm_ai_discoverability';

    /**
     * Cache tag distinguishing this cache type from all others.
     */
    public const CACHE_TAG = 'SEOCOMM_AI_DISCOVERABILITY';

    /**
     * @param FrontendPool $cacheFrontendPool cache type frontend pool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
