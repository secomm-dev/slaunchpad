<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Service\OrderPlacementAuthorization;

/**
 * OrderPlacementAuthorization (corrective TASK-EDS9T5 Blocker 3):
 * the grant is bound to the exact (quote_id, attempt entity_id,
 * app_trans_id) triple — there is NO quote-only consumption API anymore.
 *
 *  - peekForQuote() exposes the full triple WITHOUT consuming (the guard
 *    validates it against the PERSISTED attempt before consuming);
 *  - only consumeIfMatches() (full triple) consumes, single-use;
 *  - a grant still open is a programming error (grant() throws).
 */
class OrderPlacementAuthorizationTest extends TestCase
{
    private const QUOTE_ID = 42;
    private const ATTEMPT_ID = 9;
    private const APP_TRANS_ID = '260826_1000_000000123';

    /**
     * @var OrderPlacementAuthorization
     */
    private OrderPlacementAuthorization $authorization;

    protected function setUp(): void
    {
        $this->authorization = new OrderPlacementAuthorization();
    }

    /**
     * No grant open: peek returns null (nothing authorizes anything).
     *
     * @return void
     */
    public function testPeekWithoutGrantReturnsNull(): void
    {
        $this->assertNull($this->authorization->peekForQuote(self::QUOTE_ID));
        $this->assertFalse($this->authorization->isOpen());
    }

    /**
     * Peek exposes the FULL triple for validation and does NOT consume:
     * the grant stays open afterwards.
     *
     * @return void
     */
    public function testPeekReturnsFullTripleWithoutConsuming(): void
    {
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);

        $grant = $this->authorization->peekForQuote(self::QUOTE_ID);

        $this->assertSame(
            ['quote_id' => self::QUOTE_ID, 'attempt_id' => self::ATTEMPT_ID, 'app_trans_id' => self::APP_TRANS_ID],
            $grant
        );
        $this->assertTrue($this->authorization->isOpen());
        // Peek is read-only: repeated peeks are stable.
        $this->assertSame($grant, $this->authorization->peekForQuote(self::QUOTE_ID));
    }

    /**
     * A quote id alone never matches a grant for a different quote.
     *
     * @return void
     */
    public function testPeekForDifferentQuoteReturnsNull(): void
    {
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);

        $this->assertNull($this->authorization->peekForQuote(43));
        $this->assertTrue($this->authorization->isOpen());
    }

    /**
     * Exact triple consumption: single-use, clears the grant.
     *
     * @return void
     */
    public function testConsumeIfMatchesConsumesExactTripleOnce(): void
    {
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);

        $this->assertTrue(
            $this->authorization->consumeIfMatches(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID)
        );
        $this->assertFalse($this->authorization->isOpen());
        $this->assertFalse(
            $this->authorization->consumeIfMatches(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID),
            'The grant is single-use.'
        );
    }

    /**
     * Wrong attempt_id in the consumption check: refused, grant intact
     * (the guard then blocks).
     *
     * @return void
     */
    public function testConsumeWithWrongAttemptIdIsRefusedAndGrantIntact(): void
    {
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);

        $this->assertFalse($this->authorization->consumeIfMatches(self::QUOTE_ID, 10, self::APP_TRANS_ID));
        $this->assertTrue($this->authorization->isOpen());
    }

    /**
     * Wrong app_trans_id in the consumption check: refused, grant intact.
     *
     * @return void
     */
    public function testConsumeWithWrongAppTransIdIsRefusedAndGrantIntact(): void
    {
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);

        $this->assertFalse($this->authorization->consumeIfMatches(self::QUOTE_ID, self::ATTEMPT_ID, 'other'));
        $this->assertTrue($this->authorization->isOpen());
    }

    /**
     * Wrong quote in the consumption check: refused, grant intact.
     *
     * @return void
     */
    public function testConsumeWithWrongQuoteIsRefusedAndGrantIntact(): void
    {
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);

        $this->assertFalse($this->authorization->consumeIfMatches(43, self::ATTEMPT_ID, self::APP_TRANS_ID));
        $this->assertTrue($this->authorization->isOpen());
    }

    /**
     * A grant still open when granting again is a programming error.
     *
     * @return void
     */
    public function testGrantWhileOpenThrows(): void
    {
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already open');
        $this->authorization->grant(43, 10, 'other');
    }

    /**
     * clear() drops any open grant (OrderFinalizer's finally block).
     *
     * @return void
     */
    public function testClearDropsOpenGrant(): void
    {
        $this->authorization->grant(self::QUOTE_ID, self::ATTEMPT_ID, self::APP_TRANS_ID);
        $this->authorization->clear();

        $this->assertFalse($this->authorization->isOpen());
        $this->assertNull($this->authorization->peekForQuote(self::QUOTE_ID));
    }

    /**
     * The quote-only consumeForQuote API no longer exists — its removal is
     * the corrective Blocker 3 contract.
     *
     * @return void
     */
    public function testQuoteOnlyConsumeApiIsRemoved(): void
    {
        $this->assertFalse(
            method_exists(OrderPlacementAuthorization::class, 'consumeForQuote'),
            'consumeForQuote (quote-only authorization) must not exist.'
        );
    }
}
