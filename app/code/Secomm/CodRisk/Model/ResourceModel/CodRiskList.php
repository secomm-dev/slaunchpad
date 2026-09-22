<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CodRiskList extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('secomm_cod_risk_list', 'entity_id');
    }
}