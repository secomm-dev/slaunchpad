<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Cache\Type;

use Magento\Framework\Cache\Frontend\Decorator\TagScope;
use Magento\Framework\App\Cache\Type\FrontendPool;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — cache type for the persistent canonical zone registry
 * (`secomm_shippingcore_zones`). Invalidated ONLY by zone mutations through the
 * CanonicalZoneRepository (create / update / enable-disable / delete).
 */
class Zone extends TagScope
{
    public const TYPE_ID = 'secomm_shippingcore_zones';

    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct(
            $cacheFrontendPool->get(self::TYPE_ID),
            self::TYPE_ID
        );
    }
}
