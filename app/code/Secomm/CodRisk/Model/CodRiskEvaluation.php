<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\CodRisk\Model\ResourceModel\CodRiskEvaluation as CodRiskEvaluationResource;

/**
 * Evaluation log — only WARNING/BLOCK rows are written (CR-010, spec nguồn §20.3).
 */
class CodRiskEvaluation extends AbstractModel
{
    protected $_cacheTag = 'secomm_cod_risk_evaluation';
    protected $_eventPrefix = 'secomm_cod_risk_evaluation';

    protected function _construct(): void
    {
        $this->_init(CodRiskEvaluationResource::class);
    }
}