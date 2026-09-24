<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Evaluation\Rule;

use Secomm\CodRisk\Api\CodRiskRuleInterface;
use Secomm\CodRisk\Api\Data\CodRiskContextInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;
use Secomm\CodRisk\Model\Config;
use Secomm\CodRisk\Model\Evaluation\RuleCodes;
use Secomm\CodRisk\Model\Evaluation\RuleResult;
use Secomm\CodRisk\Model\Service\EventCounter;

/**
 * P1 precedence 20 — burst of customer-attributable events within a short window
 * (spec nguồn §12, D-03). Counts the indexed event store, never sales_order.
 * A spam match does NOT auto-blacklist (D-08 boundary).
 */
class SpamOrderRule implements CodRiskRuleInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly EventCounter $eventCounter,
    ) {
    }

    public function evaluate(CodRiskContextInterface $context): RuleResult
    {
        $phone = (string)$context->getNormalizedPhone();
        $websiteId = $context->getWebsiteId();

        if ($phone === '' || !$this->config->isSpamEnabled($websiteId)) {
            return RuleResult::noMatch(RuleCodes::SPAM_ORDER);
        }

        $since = new \DateTimeImmutable(
            '-' . $this->config->getSpamLookbackDays($websiteId) . ' days',
            new \DateTimeZone('UTC')
        );
        $count = $this->eventCounter->countEvents(
            $phone,
            $websiteId,
            $since,
            $this->config->getSpamAttributableReasons($websiteId)
        );

        if ($count < $this->config->getSpamThreshold($websiteId)) {
            return RuleResult::noMatch(RuleCodes::SPAM_ORDER);
        }

        return RuleResult::match(
            RuleCodes::SPAM_ORDER,
            CodRiskDecisionInterface::BLOCK,
            RuleCodes::REASON_SPAM_MATCHED,
            terminal: true
        );
    }
}
