<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Audit;

use Magento\Framework\Model\AbstractModel;
use Secomm\CodRisk\Model\ResourceModel\AuditLog as AuditLogResource;

/**
 * Administrative audit row: who changed what, when (spec nguồn §16).
 */
class AuditLog extends AbstractModel
{
    public const ENTITY_TYPE_LIST = 'list';
    public const ENTITY_TYPE_EVENT = 'event';
    public const ENTITY_TYPE_OVERRIDE = 'override';
    public const ENTITY_TYPE_CONFIG = 'config';

    protected $_cacheTag = 'secomm_cod_risk_audit';
    protected $_eventPrefix = 'secomm_cod_risk_audit';

    protected function _construct(): void
    {
        $this->_init(AuditLogResource::class);
    }
}