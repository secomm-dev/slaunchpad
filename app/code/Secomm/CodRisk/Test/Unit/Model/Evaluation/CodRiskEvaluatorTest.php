<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Test\Unit\Model\Evaluation;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Secomm\CodRisk\Api\Data\CodRiskContextInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;
use Secomm\CodRisk\Model\CodRiskEvaluation;
use Secomm\CodRisk\Model\CodRiskEvaluationFactory;
use Secomm\CodRisk\Model\Data\CodRiskContext;
use Secomm\CodRisk\Model\Evaluation\CodRiskEvaluator;
use Secomm\CodRisk\Model\Evaluation\RuleCodes;
use Secomm\CodRisk\Model\Evaluation\RulePool;
use Secomm\CodRisk\Model\Evaluation\RuleResult;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;
use Secomm\CodRisk\Model\Service\EvaluationLogger;

/**
 * CR-003 precedence + CR-008 invalid phone + CR-010 trace behavior.
 */
class CodRiskEvaluatorTest extends TestCase
{
    private CodRiskEvaluator $evaluator;
    private MockObject $evaluationFactory;

    protected function setUp(): void
    {
        $this->evaluationFactory = $this->createMock(CodRiskEvaluationFactory::class);
        $evaluationLogger = new EvaluationLogger($this->evaluationFactory, new NullLogger());
        $this->evaluator = new CodRiskEvaluator(
            new RulePool([]),
            $evaluationLogger
        );
    }

    private function makeEvaluator(RulePool $pool): CodRiskEvaluator
    {
        return new CodRiskEvaluator($pool, new EvaluationLogger($this->evaluationFactory, new NullLogger()));
    }

    public function testInvalidPhoneYieldsAllowWithoutTrace(): void
    {
        $this->evaluationFactory->expects($this->never())->method('create');

        $decision = $this->evaluator->evaluate($this->context('09012'));

        $this->assertSame(CodRiskDecisionInterface::ALLOW, $decision->getDecision());
        $this->assertSame(CodRiskDecisionInterface::REASON_NO_PHONE, $decision->getReasonCode());
    }

    public function testFirstTerminalRuleWins(): void
    {
        // Blacklist (10) matched terminally -> allowlist/historical below never consulted.
        $blacklist = $this->stubRule(RuleCodes::BLACKLIST, RuleResult::match(
            RuleCodes::BLACKLIST,
            CodRiskDecisionInterface::BLOCK,
            'blacklisted'
        ));
        $allowlist = $this->stubRule(RuleCodes::ALLOWLIST, RuleResult::match(
            RuleCodes::ALLOWLIST,
            CodRiskDecisionInterface::ALLOW
        ));

        $evaluator = $this->makeEvaluator(new RulePool([$blacklist, $allowlist]));
        $decision = $evaluator->evaluate($this->context('0901234567'));

        $this->assertSame(CodRiskDecisionInterface::BLOCK, $decision->getDecision());
        $this->assertSame(RuleCodes::BLACKLIST, $decision->getMatchedRule());
    }

    public function testWarningTraceIsLogged(): void
    {
        $evaluation = $this->createMock(CodRiskEvaluation::class);
        $evaluation->method('save')->willReturn(null);
        $this->evaluationFactory->method('create')->willReturn($evaluation);

        $historical = $this->stubRule(RuleCodes::HISTORICAL, RuleResult::match(
            RuleCodes::HISTORICAL,
            CodRiskDecisionInterface::WARNING,
            RuleCodes::REASON_HISTORICAL_WARNING,
            2
        ));

        $evaluator = $this->makeEvaluator(new RulePool([$historical]));
        $decision = $evaluator->evaluate($this->context('0901234567'));

        $this->assertSame(CodRiskDecisionInterface::WARNING, $decision->getDecision());
        $this->assertSame(2, $decision->getHistoricalCount());
    }

    private function stubRule(string $code, RuleResult $result): object
    {
        return new class($result) implements \Secomm\CodRisk\Api\CodRiskRuleInterface {
            public function __construct(
                private readonly RuleResult $result,
            ) {
            }

            public function evaluate(CodRiskContextInterface $context): RuleResult
            {
                return $this->result;
            }
        };
    }

    private function context(string $rawPhone): CodRiskContext
    {
        return new CodRiskContext(new PhoneNormalizer(), $rawPhone, 1, 100, null, null);
    }
}
