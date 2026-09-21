<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Fallback;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Fallback\FallbackRateInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackRateProviderInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackRateRequestInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackRate;
use Secomm\ShippingCore\Model\Fallback\FallbackRateProviderPool;
use Secomm\ShippingCore\Model\Fallback\FallbackRateRequest;

/**
 * TASK-XXBN5X — optional provider registration: zero-provider valid, order preserved,
 * contract guard; provider stub semantics (match → rate, no match → null).
 */
class FallbackRateProviderPoolTest extends TestCase
{
    public function testZeroProvidersIsValidState(): void
    {
        $pool = new FallbackRateProviderPool([]);

        $this->assertSame([], $pool->getProviders());
    }

    public function testSingleProviderIsRetrivableInOrder(): void
    {
        $first = $this->stubProvider();
        $second = $this->stubProvider();

        $pool = new FallbackRateProviderPool([$first, $second]);

        $this->assertSame([$first, $second], $pool->getProviders());
    }

    public function testRejectsInvalidProviderEntry(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must implement');
        new FallbackRateProviderPool([new \stdClass()]);
    }

    public function testProviderStubReturnsRateOnMatchAndNullOnNoMatch(): void
    {
        $provider = $this->stubProvider(returnsRate: true);
        $request = new FallbackRateRequest('VN', 521, '70000', 1.0, 500000.0, 1.0, 1);

        $rate = $provider->getRate('STANDARD', $request);
        $this->assertInstanceOf(FallbackRateInterface::class, $rate);
        $this->assertSame(40000.0, $rate->getAmount());

        $noRate = $this->stubProvider(returnsRate: false)->getRate('STANDARD', $request);
        $this->assertNull($noRate);
    }

    private function stubProvider(bool $returnsRate = false): FallbackRateProviderInterface
    {
        return new readonly class ($returnsRate) implements FallbackRateProviderInterface {
            public function __construct(private bool $returnsRate)
            {
            }

            public function getRate(
                string $serviceLevel,
                FallbackRateRequestInterface $request
            ): ?FallbackRateInterface {
                if (!$this->returnsRate) {
                    return null;
                }

                return new FallbackRate(40000.0, 'Standard Delivery');
            }
        };
    }
}
