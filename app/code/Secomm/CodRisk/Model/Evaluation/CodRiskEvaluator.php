<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Evaluation;

use Secomm\CodRisk\Api\CodRiskEvaluatorInterface;
use Secomm\CodRisk\Api\Data\CodRiskContextInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;
use Secomm\CodRisk\Model\Data\CodRiskDecision;
use Secomm\CodRisk\Model\Service\EvaluationLogger;

/**
 * Deterministic pipeline: first terminal rule result wins; otherwise base ALLOW.
 * Historical count and spam match are surfaced on the decision for admin visibility.
 */
class CodRiskEvaluator implements CodRiskEvaluatorInterface
{
    public function __construct(
        private readonly RulePool $rulePool,
        private readonly EvaluationLogger $evaluationLogger,
    ) {
    }

    public function evaluate(CodRiskContextInterface $context): CodRiskDecisionInterface
    {
        $phone = $context->getNormalizedPhone();
        if ($phone === null || $phone === '') {
            // Invalid/missing phone is an address validation concern, never a risk
            // BLOCK (CR-008) — and it is not logged as an evaluation either.
            return new CodRiskDecision(
                CodRiskDecisionInterface::ALLOW,
                reasonCode: CodRiskDecisionInterface::REASON_NO_PHONE
            );
        }

        $decision = CodRiskDecisionInterface::ALLOW;
        $matchedRule = null;
        $reasonCode = null;
        $historicalCount = 0;
        $spamMatched = false;

        foreach ($this->rulePool->getRules() as $rule) {
            $result = $rule->evaluate($context);

            if ($result->getRuleCode() === RuleCodes::SPAM_ORDER) {
                $spamMatched = $result->isMatched();
            }
            if ($result->getRuleCode() === RuleCodes::HISTORICAL) {
                $historicalCount = $result->getHistoricalCount();
            }

            if ($result->isMatched() && $result->isTerminal()) {
                $decision = $result->getDecision();
                $matchedRule = $result->getRuleCode();
                $reasonCode = $result->getReasonCode();
                break;
            }
        }

        $decisionDto = new CodRiskDecision(
            $decision,
            $matchedRule,
            $reasonCode,
            $historicalCount,
            $spamMatched,
            $phone,
            $context->getWebsiteId()
        );

        // Trace only checkout-path evaluations (quote) — live order-view/admin
        // re-evaluations (orderId set) would duplicate rows on every visit.
        if ($context->getOrderId() === null) {
            $this->evaluationLogger->logIfSignificant($context, $decisionDto);
        }

        return $decisionDto;
    }
}
