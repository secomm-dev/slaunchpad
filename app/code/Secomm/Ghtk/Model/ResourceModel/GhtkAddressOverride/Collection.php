<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\ResourceModel\GhtkAddressOverride;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\Ghtk\Model\GhtkAddressOverride;
use Secomm\Ghtk\Model\ResourceModel\GhtkAddressOverride as GhtkAddressOverrideResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'map_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'secomm_ghtk_address_map_collection';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(GhtkAddressOverride::class, GhtkAddressOverrideResource::class);
    }
}
