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
 * P1 precedence 40 — long-term COD risk history per phone (spec nguồn §11).
 * Only events with include_snapshot=1 count (CR-004). Below the warning
 * threshold the rule yields no match and the pipeline base decision stays ALLOW.
 */
class HistoricalRiskRule implements CodRiskRuleInterface
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

        if ($phone === '') {
            return RuleResult::noMatch(RuleCodes::HISTORICAL);
        }

        $since = new \DateTimeImmutable(
            '-' . $this->config->getLookbackDays($websiteId) . ' days',
            new \DateTimeZone('UTC')
        );
        $count = $this->eventCounter->countEvents($phone, $websiteId, $since, null, includeOnly: true);

        if ($count >= $this->config->getBlockThreshold($websiteId)) {
            return RuleResult::match(
                RuleCodes::HISTORICAL,
                CodRiskDecisionInterface::BLOCK,
                RuleCodes::REASON_HISTORICAL_BLOCK,
                $count,
                terminal: true
            );
        }

        if ($count >= $this->config->getWarningThreshold($websiteId)) {
            return RuleResult::match(
                RuleCodes::HISTORICAL,
                CodRiskDecisionInterface::WARNING,
                RuleCodes::REASON_HISTORICAL_WARNING,
                $count,
                terminal: true
            );
        }

        return RuleResult::noMatch(RuleCodes::HISTORICAL, $count);
    }
}
