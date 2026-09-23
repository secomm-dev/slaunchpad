<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\CodRisk\Model\ResourceModel\CodRiskOverride as CodRiskOverrideResource;

/**
 * Manual override — per order only (D-04). Never auto-adds an Allowlist, never
 * removes a Blacklist record, never resets history (spec nguồn §14.3).
 */
class CodRiskOverride extends AbstractModel
{
    protected $_cacheTag = 'secomm_cod_risk_override';
    protected $_eventPrefix = 'secomm_cod_risk_override';

    protected function _construct(): void
    {
        $this->_init(CodRiskOverrideResource::class);
    }
}