<?php
/**
 * Unit test for the single-use order placement authorization (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Service\OrderPlacementAuthorization;

/**
 * Verifies the internal grant: single open grant, full-triple peek/consume
 * semantics, single use and clear.
 */
class OrderPlacementAuthorizationTest extends TestCase
{
    /**
     * A granted quote peeks with the full triple and consumes on an exact
     * match.
     *
     * @return void
     */
    public function testGrantPeekAndConsume(): void
    {
        $authorization = new OrderPlacementAuthorization();
        $this->assertFalse($authorization->isOpen());

        $authorization->grant(42, 7, 'MOMOREF');
        $this->assertTrue($authorization->isOpen());
        $this->assertSame(
            ['quote_id' => 42, 'attempt_id' => 7, 'order_ref' => 'MOMOREF'],
            $authorization->peekForQuote(42)
        );

        $this->assertTrue($authorization->consumeIfMatches(42, 7, 'MOMOREF'));
        $this->assertFalse($authorization->isOpen());
        $this->assertNull($authorization->peekForQuote(42));
    }

    /**
     * A second grant while one is open is a programming error.
     *
     * @return void
     */
    public function testSecondGrantThrows(): void
    {
        $authorization = new OrderPlacementAuthorization();
        $authorization->grant(42, 7, 'MOMOREF');

        $this->expectException(\RuntimeException::class);
        $authorization->grant(43, 8, 'MOMOOTHER');
    }

    /**
     * A partial triple match never consumes.
     *
     * @return void
     */
    public function testPartialMatchNeverConsumes(): void
    {
        $authorization = new OrderPlacementAuthorization();
        $authorization->grant(42, 7, 'MOMOREF');

        $this->assertFalse($authorization->consumeIfMatches(43, 7, 'MOMOREF'));
        $this->assertFalse($authorization->consumeIfMatches(42, 8, 'MOMOREF'));
        $this->assertFalse($authorization->consumeIfMatches(42, 7, 'MOMOOTHER'));
        $this->assertTrue($authorization->isOpen());
    }

    /**
     * A grant for another quote is invisible to peekForQuote.
     *
     * @return void
     */
    public function testPeekIsQuoteBound(): void
    {
        $authorization = new OrderPlacementAuthorization();
        $authorization->grant(42, 7, 'MOMOREF');

        $this->assertNull($authorization->peekForQuote(43));
    }

    /**
     * clear() drops an open grant (the finalizer's finally path).
     *
     * @return void
     */
    public function testClearDropsOpenGrant(): void
    {
        $authorization = new OrderPlacementAuthorization();
        $authorization->grant(42, 7, 'MOMOREF');
        $authorization->clear();

        $this->assertFalse($authorization->isOpen());
    }
}
