<?php
/**
 * Unit test for the MoMo merchant reference builder (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Model\OrderRefBuilder;

/**
 * Verifies the order_ref / requestId format: MoMo-bounded length, the
 * MOMO prefix + reserved order id correlation and the random suffix.
 */
class OrderRefBuilderTest extends TestCase
{
    /**
     * order_ref = MOMO + ymdHis + reservedOrderId + 4 hex chars.
     *
     * @return void
     */
    public function testBuildOrderRefFormat(): void
    {
        $builder = new OrderRefBuilder($this->dateTime('260918120000'));

        $orderRef = $builder->buildOrderRef('200000001');

        $this->assertMatchesRegularExpression(
            '/^MOMO260918120000200000001[0-9a-f]{4}$/',
            $orderRef
        );
        $this->assertLessThanOrEqual(64, strlen($orderRef));
    }

    /**
     * requestId derives from the order ref with an -R suffix.
     *
     * @return void
     */
    public function testBuildRequestIdDerivesFromOrderRef(): void
    {
        $builder = new OrderRefBuilder($this->dateTime('260918120000'));
        $orderRef = $builder->buildOrderRef('200000001');

        $requestId = $builder->buildRequestId($orderRef);

        $this->assertMatchesRegularExpression(
            '/^' . preg_quote($orderRef, '/') . '-R[0-9a-f]{4}$/',
            $requestId
        );
    }

    /**
     * Two refs minted in the same clock tick differ (random suffix).
     *
     * @return void
     */
    public function testConsecutiveOrderRefsDiffer(): void
    {
        $builder = new OrderRefBuilder($this->dateTime('260918120000'));

        $this->assertNotSame(
            $builder->buildOrderRef('200000001'),
            $builder->buildOrderRef('200000001')
        );
    }

    /**
     * DateTime stub pinned to a fixed gmtDate format result.
     *
     * @param string $ymdHis
     * @return DateTime&\PHPUnit\Framework\MockObject\MockObject
     */
    private function dateTime(string $ymdHis): DateTime
    {
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn($ymdHis);

        return $dateTime;
    }
}
