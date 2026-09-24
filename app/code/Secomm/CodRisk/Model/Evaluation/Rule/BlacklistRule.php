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
use Secomm\CodRisk\Model\CodRiskList;
use Secomm\CodRisk\Model\Evaluation\RuleCodes;
use Secomm\CodRisk\Model\Evaluation\RuleResult;
use Secomm\CodRisk\Model\Service\ListLookup;

/**
 * P1 precedence 10 — explicit business decision (D-10 admin grid). Runs first so
 * no Allowlist/Historical result can bypass it (CR-001/CR-003).
 */
class BlacklistRule implements CodRiskRuleInterface
{
    public function __construct(
        private readonly ListLookup $listLookup,
    ) {
    }

    public function evaluate(CodRiskContextInterface $context): RuleResult
    {
        $phone = (string)$context->getNormalizedPhone();
        if ($phone === '') {
            return RuleResult::noMatch(RuleCodes::BLACKLIST);
        }

        $record = $this->listLookup->findActive($phone, $context->getWebsiteId(), CodRiskList::LIST_TYPE_BLOCK);

        if ($record === null) {
            return RuleResult::noMatch(RuleCodes::BLACKLIST);
        }

        return RuleResult::match(
            RuleCodes::BLACKLIST,
            CodRiskDecisionInterface::BLOCK,
            (string)($record->getData('reason') ?? RuleCodes::REASON_BLACKLISTED),
            terminal: true
        );
    }
}
