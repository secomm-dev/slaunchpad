<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\ResourceModel\AuditLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\CodRisk\Model\Audit\AuditLog;
use Secomm\CodRisk\Model\ResourceModel\AuditLog as AuditLogResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(AuditLog::class, AuditLogResource::class);
    }
}
