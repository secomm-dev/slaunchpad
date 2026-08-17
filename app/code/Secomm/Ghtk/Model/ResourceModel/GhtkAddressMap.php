<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Secomm\Ghtk\Api\Data\GhtkAddressMapInterface;

class GhtkAddressMap extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'secomm_ghtk_address_map_resource';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('secomm_ghtk_address_map', GhtkAddressMapInterface::MAP_ID);
        $this->_useIsObjectNew = true;
    }
}
