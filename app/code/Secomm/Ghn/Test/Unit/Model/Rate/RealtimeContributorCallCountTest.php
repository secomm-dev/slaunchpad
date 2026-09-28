<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Rate;

use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Rate\GhnRateCalculator;
use Secomm\Ghn\Model\Rate\GhnRateRequestMapper;
use Secomm\Ghn\Model\Rate\RealtimeRateContributor;
use Secomm\Ghn\Model\Rate\RealtimeRateContributorFactory;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;

/**
 * TASK-SEC-D Phase 5 — direct call-count instrumentation for the wired GHN realtime tail:
 * ONE execution → ONE contributor call → ONE calculator (mapping + provider API) call.
 * The calculator is spy'd at its public boundary (`quoteWithHandoff`), which is the single
 * entry to mapping + Fee API — no contributor-level proxy inference.
 */
class RealtimeContributorCallCountTest extends TestCase
{
    public function testOneDecisionYieldsExactlyOneContributorAndOneCalculatorCall(): void
    {
        $calculatorCalls = 0;
        $calculator = $this->createMock(GhnRateCalculator::class);
        $calculator->method('quoteWithHandoff')->willReturnCallback(
            function ($query, $handoff) use (&$calculatorCalls): CarrierRateOutcomeInterface {
                $calculatorCalls++;

                return CarrierRateOutcome::success($this->createMock(\Secomm\ShippingCore\Api\Rate\CarrierRateInterface::class));
            }
        );
        $mapper = $this->createMock(GhnRateRequestMapper::class);
        $mapper->method('map')->willReturnCallback(
            function (): \Secomm\Ghn\Model\Rate\GhnRateQuery {
                // GhnRateQuery is final — build a real one with minimal in-range values.
                $estimate = new \Secomm\Ghn\Model\Rate\QuoteParcelEstimate([]);

                return new \Secomm\Ghn\Model\Rate\GhnRateQuery('VN', 0, null, null, $estimate);
            }
        );
        $logger = new \Secomm\Ghn\Model\Logger\GhnLogger(new \Psr\Log\NullLogger());

        $request = new RateRequest();
        $factory = new RealtimeRateContributorFactory($calculator, $mapper, $logger);
        $contributor = $factory->create($request);

        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $handoff->method('isApplicable')->willReturn(true);
        $handoff->method('getResolvedAddress')->willReturn(
            $this->createMock(\Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface::class)
        );

        $contributor->contribute('secomm_ghn', $handoff);

        $this->assertSame(1, $calculatorCalls, 'ONE decision = exactly ONE calculator (mapping+API) call');
    }

    public function testWrongCarrierCodeIsAWiringErrorNotARateResult(): void
    {
        $calculator = $this->createMock(GhnRateCalculator::class);
        $calculator->expects($this->never())->method('quoteWithHandoff');
        $factory = new RealtimeRateContributorFactory(
            $calculator,
            $this->createMock(GhnRateRequestMapper::class),
            new \Secomm\Ghn\Model\Logger\GhnLogger(new \Psr\Log\NullLogger())
        );
        $contributor = $factory->create(new RateRequest());

        $this->expectException(\LogicException::class);
        $contributor->contribute('other_carrier', $this->createMock(CarrierAddressHandoffInterface::class));
    }

    public function testMissingRequestIsAWiringErrorNotARateResult(): void
    {
        // Missing request = a programming/wiring mistake — instantiate the contributor
        // directly (the factory always supplies the current request).
        $contributor = new \Secomm\Ghn\Model\Rate\RealtimeRateContributor(
            $this->createMock(GhnRateCalculator::class),
            $this->createMock(GhnRateRequestMapper::class),
            new \Secomm\Ghn\Model\Logger\GhnLogger(new \Psr\Log\NullLogger()),
            null
        );

        $this->expectException(\LogicException::class);
        $contributor->contribute('secomm_ghn', $this->createMock(CarrierAddressHandoffInterface::class));
    }
}
