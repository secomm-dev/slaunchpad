<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\CodRisk\Model\ResourceModel\CodRiskEvent as CodRiskEventResource;

/**
 * Normalized COD risk event. `include_snapshot` is frozen at record time
 * (spec nguồn §13) — later reason config changes never reinterpret old events.
 */
class CodRiskEvent extends AbstractModel
{
    protected $_cacheTag = 'secomm_cod_risk_event';
    protected $_eventPrefix = 'secomm_cod_risk_event';

    protected function _construct(): void
    {
        $this->_init(CodRiskEventResource::class);
    }
}