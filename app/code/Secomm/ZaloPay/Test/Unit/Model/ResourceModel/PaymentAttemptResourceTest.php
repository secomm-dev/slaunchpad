<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * TASK-CG6BM7 email dispatch claim (HIGH: duplicate email race).
 *
 * These tests pin the SQL semantics the concurrency design depends on:
 *  - the claim is a SINGLE conditional UPDATE (atomic at the DB level):
 *    it only matches a row whose email_dispatch is NULL or stale beyond
 *    the grace period, so a live claim can never be double-granted;
 *  - the release is token-guarded: a stale owner whose claim was taken
 *    over by a newer sender can never release the newer sender's claim.
 */
class PaymentAttemptResourceTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var PaymentAttemptResource
     */
    private $resource;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);

        // Partial mock: only the DB seam is mocked - the where-clause
        // construction (the actual concurrency contract) runs for real.
        $this->resource = $this->getMockBuilder(PaymentAttemptResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getMainTable')->willReturn('secomm_zalopay_payment_attempt');
    }

    /**
     * EMAIL A: the claim UPDATE is conditional - it only matches
     * email_dispatch IS NULL OR stale beyond the grace period.
     */
    public function testClaimEmailDispatchIssuesSingleConditionalUpdate(): void
    {
        $token = 1690000000;
        $grace = 900;
        // 1690000000 - 900: claims older than the grace window are reclaimable.
        $expectedWhere = 'entity_id = 9 AND (email_dispatch IS NULL OR email_dispatch <= 1689999100)';

        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                'secomm_zalopay_payment_attempt',
                [PaymentAttemptInterface::EMAIL_DISPATCH => $token],
                $expectedWhere
            )
            ->willReturn(1);

        $this->assertTrue($this->resource->claimEmailDispatch(9, $token, $grace));
    }

    /**
     * EMAIL B: a row already claimed (and inside the grace window) matches
     * no row - the losing concurrent finalizer gets false and must NOT send.
     */
    public function testClaimEmailDispatchReturnsFalseWhenClaimHeld(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->willReturn(0);

        $this->assertFalse($this->resource->claimEmailDispatch(9, 1690000000, 900));
    }

    /**
     * EMAIL C: the release only clears THIS caller's own token - a claim
     * taken over by a newer sender (different token) is never released by
     * a stale owner finishing late.
     */
    public function testReleaseEmailDispatchIsTokenGuarded(): void
    {
        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                'secomm_zalopay_payment_attempt',
                [PaymentAttemptInterface::EMAIL_DISPATCH => null],
                'entity_id = 9 AND email_dispatch = 1690000000'
            );

        $this->resource->releaseEmailDispatch(9, 1690000000);
    }
}
