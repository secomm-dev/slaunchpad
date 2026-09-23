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
 * P1 precedence 30 — controlled business exception. Bypasses the Historical rule
 * only: running after Blacklist/Spam guarantees it can never bypass those (CR-002).
 */
class AllowlistRule implements CodRiskRuleInterface
{
    public function __construct(
        private readonly ListLookup $listLookup,
    ) {
    }

    public function evaluate(CodRiskContextInterface $context): RuleResult
    {
        $phone = (string)$context->getNormalizedPhone();
        if ($phone === '') {
            return RuleResult::noMatch(RuleCodes::ALLOWLIST);
        }

        $record = $this->listLookup->findActive($phone, $context->getWebsiteId(), CodRiskList::LIST_TYPE_ALLOW);

        if ($record === null) {
            return RuleResult::noMatch(RuleCodes::ALLOWLIST);
        }

        return RuleResult::match(
            RuleCodes::ALLOWLIST,
            CodRiskDecisionInterface::ALLOW,
            (string)($record->getData('reason') ?? ''),
            terminal: true
        );
    }
}
