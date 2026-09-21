<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Tracking;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderRateLimitException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Tracking\GhnStatusMapper;
use Secomm\Ghn\Model\Tracking\GhnTrackingFetcher;
use Secomm\Ghn\Model\Tracking\GhnTrackingResultBuilder;
use Secomm\ShippingCore\Api\Tracking\TrackingUpdateInterface;
use Secomm\ShippingCore\Model\Tracking\ShipmentTrackingProcessor;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * TASK-PWHG0V (GHN-E3-A) — the Magento tracking result contract: ONE mapper (the real
 * GhnStatusMapper backs every assertion), provider not-found AND transport failures resolve to
 * a safe `Error` result (no exception escapes), reconcile-through-processor is non-fatal, and
 * the display carries only curated fields (normalized label + raw status; no raw payload).
 */
class GhnTrackingResultBuilderTest extends TestCase
{
    private GhnTrackingFetcher&MockObject $fetcher;

    private ShipmentTrackingProcessor&MockObject $processor;

    private GhnTrackingResultBuilder $builder;

    protected function setUp(): void
    {
        $this->fetcher = $this->createMock(GhnTrackingFetcher::class);
        $this->processor = $this->createMock(ShipmentTrackingProcessor::class);
        $this->builder = new GhnTrackingResultBuilder(
            $this->fetcher,
            new GhnStatusMapper(), // the ONE mapper — real instance by design
            $this->processor
        );
    }

    public function testBlankTrackingNumberYieldsFalse(): void
    {
        $this->assertFalse($this->builder->build('   ', 'GHN'));
    }

    public function testSuccessBuildsStatusWithNormalizedLabelAndRawStatus(): void
    {
        $this->givenUpdate(new TrackingUpdate(
            carrierCode: 'secomm_ghn',
            trackingNumber: 'L8TAKR',
            normalizedStatus: 'DELIVERED',
            carrierStatusCode: 'delivered',
            carrierStatusMessage: null,
            occurredAt: null,
            source: 'api',
            raw: ['status' => 'delivered']
        ));

        $status = $this->onlyStatus($this->builder->build('L8TAKR', 'GHN Delivery'));

        $this->assertSame('L8TAKR', $status->getTracking());
        $this->assertSame('GHN Delivery', $status->getCarrierTitle());
        $this->assertSame('secomm_ghn', $status->getCarrier());
        $this->assertSame('Delivered (GHN status: delivered)', $status->getTrackSummary());
        $this->assertSame('Delivered (GHN status: delivered)', $status->getStatus());
        $this->assertEmpty($status->getUrl()); // §9: no URL until docs-verified
    }

    public function testLostAndDamagedRenderTheirOwnLabels(): void
    {
        foreach (['lost' => 'Lost', 'damage' => 'Damaged'] as $raw => $label) {
            $this->setUp();
            $this->givenUpdate(new TrackingUpdate(
                carrierCode: 'secomm_ghn',
                trackingNumber: 'L8TAKR',
                normalizedStatus: $raw === 'lost' ? 'LOST' : 'DAMAGED',
                carrierStatusCode: $raw,
                carrierStatusMessage: null,
                occurredAt: null,
                source: 'api',
                raw: ['status' => $raw]
            ));

            $status = $this->onlyStatus($this->builder->build('L8TAKR', 'GHN'));
            $this->assertSame($label . ' (GHN status: ' . $raw . ')', $status->getTrackSummary());
        }
    }

    public function testExpectedDeliveryAndSanitizedProgressFromWhitelistedRaw(): void
    {
        $this->givenUpdate(new TrackingUpdate(
            carrierCode: 'secomm_ghn',
            trackingNumber: 'L8TAKR',
            normalizedStatus: 'IN_TRANSIT',
            carrierStatusCode: 'transporting',
            carrierStatusMessage: null,
            occurredAt: null,
            source: 'api',
            raw: [
                'status' => 'transporting',
                'expected_delivery_time' => '1758048000',
                'log' => [
                    ['status' => 'picked', 'time' => '1757961600', 'shipper_phone' => 'SECRET'],
                    ['status' => 'transporting', 'time' => '1758000000'],
                ],
            ]
        ));

        $status = $this->onlyStatus($this->builder->build('L8TAKR', 'GHN'));

        $this->assertSame(date('Y-m-d', 1758048000), $status->getDeliverydate());
        $this->assertSame(date('H:i:s', 1758048000), $status->getDeliverytime());

        $progress = $status->getProgressdetail();
        $this->assertCount(2, $progress);
        $this->assertStringContainsString('Picked up', (string) $progress[0]['activity']);
        $this->assertStringContainsString('picked', (string) $progress[0]['activity']);
        // Curated keys only — raw payload/PII keys never surface (brief §7/§37).
        foreach ($progress as $entry) {
            $this->assertSame(
                ['deliverydate', 'deliverytime', 'activity'],
                array_keys($entry),
                'progress entries must be curated'
            );
        }
    }

    public function testProviderNotFoundYieldsSafeErrorResult(): void
    {
        $this->fetcher->method('fetch')->willReturn(null);
        $this->processor->expects($this->never())->method('process');

        $result = $this->builder->build('L8UNKNOWN', 'GHN');
        $this->assertInstanceOf(\Magento\Shipping\Model\Tracking\Result::class, $result);
        $trackings = $result->getAllTrackings();
        $this->assertCount(1, $trackings);
        $this->assertInstanceOf(\Magento\Shipping\Model\Tracking\Result\Error::class, $trackings[0]);
        $this->assertSame('L8UNKNOWN', $trackings[0]->getTracking());
    }

    public function testProviderTimeoutYieldsSafeErrorResultWithoutCrash(): void
    {
        // brief §10/§34-D: transport failure must never crash the popup/admin.
        $this->fetcher->method('fetch')->willThrowException(
            new ProviderTimeoutException(new \Magento\Framework\Phrase('GHN order_info timed out'))
        );
        $this->processor->expects($this->never())->method('process');

        $result = $this->builder->build('L8TAKR', 'GHN');
        $trackings = $result->getAllTrackings();
        $this->assertCount(1, $trackings);
        $this->assertInstanceOf(\Magento\Shipping\Model\Tracking\Result\Error::class, $trackings[0]);
    }

    public function testProviderAuthExceptionYieldsSafeErrorResult(): void
    {
        $this->fetcher->method('fetch')->willThrowException(
            new ProviderAuthenticationException(new \Magento\Framework\Phrase('Token is not valid!'))
        );

        $trackings = $this->builder->build('L8TAKR', 'GHN')->getAllTrackings();
        $this->assertInstanceOf(\Magento\Shipping\Model\Tracking\Result\Error::class, $trackings[0]);
    }

    public function testProviderRateLimitExceptionYieldsSafeErrorResult(): void
    {
        $this->fetcher->method('fetch')->willThrowException(
            new ProviderRateLimitException(new \Magento\Framework\Phrase('too many requests'))
        );

        $trackings = $this->builder->build('L8TAKR', 'GHN')->getAllTrackings();
        $this->assertInstanceOf(\Magento\Shipping\Model\Tracking\Result\Error::class, $trackings[0]);
    }

    public function testProgrammingErrorIsNotMaskedAsProviderOutage(): void
    {
        // The exception boundary: only GhnApiException becomes a safe Error result. A PHP
        // Error (bug) must propagate fail-loud — never rendered as "provider unavailable".
        $this->fetcher->method('fetch')->willThrowException(new \TypeError('carrier code reassignment'));

        $this->expectException(\TypeError::class);
        $this->builder->build('L8TAKR', 'GHN');
    }

    public function testReconcileProgrammingErrorIsNotSwallowed(): void
    {
        // Reconciliation is best-effort for \Exception only — PHP Errors stay visible.
        $this->givenUpdate($this->sampleUpdate());
        $this->processor->method('process')->willThrowException(new \Error('processor bug'));

        $this->expectException(\Error::class);
        $this->builder->build('L8TAKR', 'GHN');
    }

    public function testReconcileFailureDoesNotBreakDisplay(): void
    {
        $update = $this->givenUpdate($this->sampleUpdate());
        $this->processor->method('process')->willThrowException(new \RuntimeException('db down'));

        $status = $this->onlyStatus($this->builder->build('L8TAKR', 'GHN'));
        $this->assertSame($update->getTrackingNumber(), $status->getTracking());
    }

    public function testSuccessfulFetchFeedsTheE1Processor(): void
    {
        $update = $this->givenUpdate($this->sampleUpdate());
        $this->processor->expects($this->once())->method('process')->with($update)->willReturn(true);

        $this->onlyStatus($this->builder->build('L8TAKR', 'GHN'));
    }

    // ---------- helpers ----------

    private function sampleUpdate(): TrackingUpdate
    {
        return new TrackingUpdate(
            carrierCode: 'secomm_ghn',
            trackingNumber: 'L8TAKR',
            normalizedStatus: 'OUT_FOR_DELIVERY',
            carrierStatusCode: 'delivering',
            carrierStatusMessage: null,
            occurredAt: null,
            source: 'api',
            raw: ['status' => 'delivering']
        );
    }

    private function givenUpdate(TrackingUpdate $update): TrackingUpdate
    {
        $this->fetcher->method('fetch')->willReturn($update);

        return $update;
    }

    private function onlyStatus(\Magento\Shipping\Model\Tracking\Result|false $result): \Magento\Shipping\Model\Tracking\Result\Status
    {
        $this->assertInstanceOf(\Magento\Shipping\Model\Tracking\Result::class, $result);
        $trackings = $result->getAllTrackings();
        $this->assertCount(1, $trackings);
        $this->assertInstanceOf(\Magento\Shipping\Model\Tracking\Result\Status::class, $trackings[0]);

        return $trackings[0];
    }
}
