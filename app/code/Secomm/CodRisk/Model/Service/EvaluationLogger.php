<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Service;

use Psr\Log\LoggerInterface;
use Secomm\CodRisk\Api\Data\CodRiskContextInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;
use Secomm\CodRisk\Model\CodRiskEvaluationFactory;
use Secomm\CodRisk\Model\Config;
use Secomm\CodRisk\Model\Service\EventCounter;

/**
 * Persists evaluation traces. Only WARNING/BLOCK rows are logged (CR-010,
 * spec nguồn §20.3 — ALLOW traces are not stored unless a business need appears).
 */
class EvaluationLogger
{
    public function __construct(
        private readonly CodRiskEvaluationFactory $evaluationFactory,
        private readonly LoggerInterface $logger,
        private readonly EventCounter $eventCounter,
        private readonly Config $config,
    ) {
    }

    public function logIfSignificant(
        CodRiskContextInterface $context,
        CodRiskDecisionInterface $decision,
    ): void {
        if (!in_array($decision->getDecision(), [CodRiskDecisionInterface::WARNING, CodRiskDecisionInterface::BLOCK], true)) {
            return;
        }

        try {
            $evaluation = $this->evaluationFactory->create();
            $evaluation->setData([
                'normalized_phone' => (string)$decision->getNormalizedPhone(),
                'website_id' => (int)($decision->getWebsiteId() ?? 0),
                'decision' => $decision->getDecision(),
                'matched_rule' => $decision->getMatchedRule(),
                'reason_code' => $decision->getReasonCode(),
                // The pipeline short-circuits before the Historical rule when a
                // higher-precedence rule matches — the trace must still record
                // the real point-in-time count, not a de-facto 0.
                'historical_count' => $this->resolveHistoricalCount($decision),
                'spam_matched' => $decision->isSpamMatched() ? 1 : 0,
                'order_id' => $context->getOrderId(),
                'quote_id' => $context->getQuoteId(),
            ]);
            $evaluation->save();
        } catch (\Throwable $e) {
            // Evaluation trace is observability, not business flow — never break checkout on it.
            $this->logger->critical(
                sprintf('[CodRisk] Failed to persist evaluation trace: %s', $e->getMessage()),
                ['exception' => $e]
            );
        }
    }

    private function resolveHistoricalCount(CodRiskDecisionInterface $decision): int
    {
        $phone = (string)$decision->getNormalizedPhone();
        if ($phone === '') {
            return 0;
        }

        $websiteId = $decision->getWebsiteId();
        $since = new \DateTimeImmutable(
            '-' . $this->config->getLookbackDays($websiteId) . ' days',
            new \DateTimeZone('UTC')
        );

        return $this->eventCounter->countEvents($phone, $websiteId, $since, null, includeOnly: true);
    }
}
