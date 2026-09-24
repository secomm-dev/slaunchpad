<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Api;

use Secomm\CodRisk\Api\Data\CodRiskContextInterface;
use Secomm\CodRisk\Model\Evaluation\RuleResult;

/**
 * Contract for every COD risk rule (AD-02).
 *
 * Rules are registered in the RulePool via di.xml with a deterministic sortOrder
 * (business precedence). Adding a rule must not require modifying the evaluator (AD-03).
 */
interface CodRiskRuleInterface
{
    /**
     * @param CodRiskContextInterface $context
     * @return RuleResult Match (optionally terminal) or no-match "continue" result.
     */
    public function evaluate(CodRiskContextInterface $context): RuleResult;
}