<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * TASK-Z6SK3T — System / Cache Management / Cache type "Secomm Address City".
 *
 * Backs the `city-data` customer section dataset (region→city tree). Entries are saved
 * through this tagged frontend so `cache:clean secomm_address_city` purges them — the
 * section previously saved untagged entries on the default frontend, which only
 * `cache:flush` could remove (a 1h stale window after a VN scheme import).
 *
 * House pattern: Secomm\GhnAddressMapper\Model\Cache\Type.
 */
class Type extends TagScope
{
    /**
     * Cache type code unique among all cache types
     */
    public const TYPE_IDENTIFIER = 'secomm_address_city';

    /**
     * Cache tag used to distinguish the cache type from all other cache
     */
    public const CACHE_TAG = 'secomm_address_city';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
