<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Api;

use Secomm\CodRisk\Api\Data\CodRiskContextInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;

/**
 * Evaluates COD risk for a normalized shipping phone.
 *
 * Consumers (e.g. the COD availability plugin in this module) call this contract —
 * they never query blacklist data or count history themselves (AD-01, SPEC-TASK-YPWH9B).
 *
 * Deterministic: same context + same risk state => same decision.
 */
interface CodRiskEvaluatorInterface
{
    /**
     * @param CodRiskContextInterface $context
     * @return CodRiskDecisionInterface Always a usable decision (invalid/missing phone
     *                                   yields ALLOW with reason code, never a BLOCK — CR-008).
     */
    public function evaluate(CodRiskContextInterface $context): CodRiskDecisionInterface;
}