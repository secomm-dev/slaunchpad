<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Gateway\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Helper\RefundQuerySubjectBuilder;
use Secomm\ZaloPay\Model\RefundModel;

/**
 * UNIT - RefundQuerySubjectBuilder (TASK-MCHN2T).
 *
 * The shared v2/query_refund subject builder: stored payload decoding,
 * terminal LocalizedException on malformed payloads, timestamp refresh and
 * re-signing through the canonical Authorization helper (no duplicated
 * MAC logic). Covers the contract the RefundCronjob and the read-only
 * zalopay:diagnose CLI both rely on.
 */
class RefundQuerySubjectBuilderTest extends TestCase
{
    private const ROW_ID = 7;

    private const M_REFUND_ID = '260916_1000_777';

    private const NOW_MS = 1800000000000;

    private Json|MockObject $serializer;

    private DateTime|MockObject $dateTime;

    private Authorization|MockObject $authorization;

    private RefundModel|MockObject $refund;

    private RefundQuerySubjectBuilder $builder;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(Json::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->authorization = $this->createMock(Authorization::class);
        $this->refund = $this->getMockBuilder(RefundModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->addMethods(['getAdditionalInformation'])
            ->getMock();
        $this->builder = new RefundQuerySubjectBuilder(
            $this->serializer,
            $this->dateTime,
            $this->authorization
        );
    }

    /**
     * Stored payload -> subject with a refreshed timestamp and a fresh MAC.
     * The OLD MAC must be removed BEFORE re-signing (asserted at the
     * Authorization boundary).
     */
    public function testBuildsResignedSubjectFromStoredPayload(): void
    {
        $stored = ['app_id' => '1000', 'm_refund_id' => self::M_REFUND_ID, 'timestamp' => 1690000000000];
        $this->serializer->method('unserialize')->willReturn($stored);
        $this->dateTime->method('timestamp')->willReturn(1800000000);
        $this->refund->method('getAdditionalInformation')->willReturn(json_encode($stored));
        $this->refund->method('getId')->willReturn(self::ROW_ID);

        $captured = null;
        $this->authorization->expects($this->once())->method('getMac')->willReturnCallback(
            function (array $subject) use (&$captured): string {
                $captured = $subject;

                return 'RESIGNED-MAC';
            }
        );

        $subject = $this->builder->build($this->refund);

        $this->assertSame('RESIGNED-MAC', $subject['mac']);
        $this->assertSame(self::NOW_MS, $subject['timestamp']);
        $this->assertSame(self::M_REFUND_ID, $subject['m_refund_id']);
        $this->assertSame('1000', $subject['app_id']);
        // The Authorization boundary saw the subject WITHOUT any stale MAC.
        $this->assertArrayNotHasKey('mac', $captured);
    }

    /**
     * A corrupt stored payload is terminal (non-retryable): the builder
     * surfaces LocalizedException so callers can terminate with reconcile
     * evidence (cron) or exit non-zero (CLI) instead of leaking JSON errors.
     */
    public function testMalformedStoredPayloadThrowsLocalizedException(): void
    {
        $this->serializer->method('unserialize')->willThrowException(new \InvalidArgumentException('broken JSON'));
        $this->refund->method('getAdditionalInformation')->willReturn('{not-json');
        $this->refund->method('getId')->willReturn(self::ROW_ID);

        $this->expectException(LocalizedException::class);
        $this->builder->build($this->refund);
    }

    /**
     * A payload without the provider refund identity cannot be queried -
     * terminal, same contract as a corrupt payload.
     */
    public function testPayloadWithoutRefundIdThrowsLocalizedException(): void
    {
        $this->serializer->method('unserialize')->willReturn(['app_id' => '1000', 'timestamp' => 1690000000000]);
        $this->refund->method('getAdditionalInformation')->willReturn('{"app_id":"1000"}');
        $this->refund->method('getId')->willReturn(self::ROW_ID);

        $this->expectException(LocalizedException::class);
        $this->builder->build($this->refund);
    }
}
