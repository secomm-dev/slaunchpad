<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Checkout\Model\Session;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Service\IpnProcessor;
use Secomm\ZaloPay\Service\OrderFinalizer;
use Secomm\ZaloPay\Service\PaymentAttemptLifecycle;
use Secomm\ZaloPay\Service\SuccessSessionPreparer;

/**
 * Session-ownership contract (corrective TASK-EDS9T5 Blocker 4, case 22):
 * the server-to-server path — IpnProcessor, OrderFinalizer and the shared
 * PaymentAttemptLifecycle — NEVER depends on the Magento checkout session.
 * Only the browser Return path owns customer session state, through
 * SuccessSessionPreparer (positive control below).
 *
 * IPN finalization therefore cannot read or write the customer session by
 * construction: there is no injection point for one.
 */
class SessionOwnershipContractTest extends TestCase
{
    /**
     * @return void
     */
    public function testIpnProcessorHasNoCheckoutSessionDependency(): void
    {
        $this->assertNoConstructorDependency(IpnProcessor::class);
    }

    /**
     * @return void
     */
    public function testOrderFinalizerHasNoCheckoutSessionDependency(): void
    {
        $this->assertNoConstructorDependency(OrderFinalizer::class);
    }

    /**
     * @return void
     */
    public function testPaymentAttemptLifecycleHasNoCheckoutSessionDependency(): void
    {
        $this->assertNoConstructorDependency(PaymentAttemptLifecycle::class);
    }

    /**
     * Positive control: the preparer DOES take the session — the one and
     * only sanctioned writer on the Return path.
     *
     * @return void
     */
    public function testSuccessSessionPreparerIsTheSanctionedSessionWriter(): void
    {
        $parameters = SuccessSessionPreparer::class;
        $reflection = new \ReflectionClass($parameters);
        $types = array_map(
            fn (\ReflectionParameter $parameter) => $parameter->getType()?->getName(),
            $reflection->getConstructor()->getParameters()
        );

        $this->assertContains(Session::class, $types);
    }

    /**
     * @param string $class
     * @return void
     */
    private function assertNoConstructorDependency(string $class): void
    {
        $reflection = new \ReflectionClass($class);
        $sessionParameters = array_filter(
            $reflection->getConstructor()->getParameters(),
            fn (\ReflectionParameter $parameter) => $parameter->getType()?->getName() === Session::class
        );

        $this->assertSame(
            [],
            array_values($sessionParameters),
            sprintf('%s must not depend on the checkout session.', $class)
        );
    }
}
