<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * System / Cache Management / Cache type "Secomm GHN Address Mapping" — approved mapping
 * resolutions only (SPEC-FEAT-FQWEQ3 §7: runtime = canonical code → approved mapping). Flushed
 * by re-running the mapping generation; never caches misses (fail closed must stay fail closed).
 */
class MappingCache extends TagScope
{
    public const TYPE_IDENTIFIER = 'secomm_ghn_mapping';

    public const CACHE_TAG = 'secomm_ghn_mapping';

    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
