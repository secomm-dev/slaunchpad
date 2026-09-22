<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Shipment;

use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderInvalidRequestException;
use Secomm\Ghn\Api\Exception\ProviderRateLimitException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Shipment\GhnActionOutcome;
use Secomm\Ghn\Model\Shipment\GhnCancelService;
use Secomm\Ghn\Model\Shipment\GhnShipmentRepository;
use Secomm\Ghn\Model\Logger\GhnLogger;

/**
 * TASK-4ATBC4 (GHN-E2) — the CANCEL action contract: reason enum fail-closed, HTTP-200-with-
 * result:false is NEVER success, mutation uncertainty → UNKNOWN_RESULT (no blind retry), and
 * local pre-flight (provider order must exist) without any Magento order mutation.
 */
class GhnCancelServiceTest extends TestCase
{
    private GhnApiClientInterface&MockObject $apiClient;

    private GhnShipmentRepository&MockObject $repository;

    private GhnCancelService $service;

    /** Default persistence row (property-driven — mutation-visible through the stub reference). */
    private ?array $existingRow = [
        'provider_status' => 'SUBMITTED',
        'ghn_order_code' => 'L8TKYG',
        'client_order_code' => 'GHNS10',
    ];

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(GhnApiClientInterface::class);
        $this->repository = $this->createMock(GhnShipmentRepository::class);
        $existingRow = &$this->existingRow;
        $this->repository->method('findByShipmentId')->willReturnCallback(
            function () use (&$existingRow): ?array {
                return $existingRow;
            }
        );
        $this->service = new GhnCancelService(
            $this->apiClient,
            $this->repository,
            new GhnLogger($this->createMock(\Psr\Log\LoggerInterface::class))
        );
    }

    public function testSuccessfulProviderResultIsSuccess(): void
    {
        $this->apiClient->expects($this->once())->method('post')->with(
            'cancel_order',
            'v2/switch-status/cancel',
            ['order_codes' => ['L8TKYG'], 'reason_code' => 'GHN-CO003']
        )->willReturn([
            ['order_code' => 'L8TKYG', 'result' => true, 'message' => 'OK'],
        ]);

        $outcome = $this->service->cancel($this->shipment(), 'GHN-CO003');

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(GhnActionOutcome::STATUS_SUCCESS, $outcome->getStatus());
        $this->assertSame('L8TKYG', $outcome->getProviderOrderCode());
    }

    public function testOptionalFreeTextReasonIsSent(): void
    {
        $this->apiClient->expects($this->once())->method('post')->with(
            'cancel_order',
            'v2/switch-status/cancel',
            ['order_codes' => ['L8TKYG'], 'reason_code' => 'GHN-CANCEL-OTHER', 'reason' => 'QC probe']
        )->willReturn([
            ['order_code' => 'L8TKYG', 'result' => true, 'message' => 'OK'],
        ]);

        $this->assertTrue($this->service->cancel($this->shipment(), 'GHN-CANCEL-OTHER', 'QC probe')->isSuccessful());
    }

    public function testHttp200WithResultFalseIsNeverSuccess(): void
    {
        $this->apiClient->method('post')->willReturn([
            ['order_code' => 'L8TKYG', 'result' => false, 'message' => 'Đơn đã giao'],
        ]);

        $outcome = $this->service->cancel($this->shipment(), 'GHN-CO001');

        $this->assertFalse($outcome->isSuccessful());
        $this->assertSame(GhnActionOutcome::STATUS_BUSINESS_REJECTED, $outcome->getStatus());
    }

    public function testInvalidReasonCodeFailsClosedWithoutProviderCall(): void
    {
        $this->apiClient->expects($this->never())->method('post');

        $outcome = $this->service->cancel($this->shipment(), 'WHATEVER');

        $this->assertSame(GhnActionOutcome::STATUS_BUSINESS_REJECTED, $outcome->getStatus());
        $this->assertStringContainsString('reason', (string) $outcome->getMessage());
    }

    public function testMissingProviderOrderFailsClosedBeforeProviderCall(): void
    {
        $this->existingRow = null;
        $this->apiClient->expects($this->never())->method('post');

        $outcome = $this->service->cancel($this->shipment(), 'GHN-CO003');

        $this->assertSame(GhnActionOutcome::STATUS_BUSINESS_REJECTED, $outcome->getStatus());
        $this->assertSame('NO_PROVIDER_ORDER', $outcome->getReasonCode());
    }

    public function testTimeoutYieldsUnknownResultForReconciliation(): void
    {
        $this->apiClient->method('post')->willThrowException(
            new ProviderTimeoutException(new \Magento\Framework\Phrase('timed out'))
        );

        $outcome = $this->service->cancel($this->shipment(), 'GHN-CO003');

        $this->assertSame(GhnActionOutcome::STATUS_UNKNOWN_RESULT, $outcome->getStatus());
    }

    public function test5xxYieldsUnknownResult(): void
    {
        // TASK-GKHXY1 r2: a 5xx leaves applied-vs-not-applied open — never classified as a
        // definitive failure for a mutation.
        $this->apiClient->method('post')->willThrowException(
            new ProviderServiceUnavailableException(new \Magento\Framework\Phrase('SERVER_ERROR'))
        );

        $outcome = $this->service->cancel($this->shipment(), 'GHN-CO003');

        $this->assertSame(GhnActionOutcome::STATUS_UNKNOWN_RESULT, $outcome->getStatus());
    }

    public function testConnectFailureStaysUnknownResult(): void
    {
        // r2 conservative fallback: the HTTP client cannot prove a before-send failure
        // (message-based detection would fabricate certainty), so connection-level transport
        // errors stay UNKNOWN_RESULT and are reconciled via the E1 Order Info API.
        $this->apiClient->method('post')->willThrowException(
            new ProviderRemoteException(new \Magento\Framework\Phrase('Couldn\'t resolve host api.ghn.vn'))
        );

        $outcome = $this->service->cancel($this->shipment(), 'GHN-CO003');

        $this->assertSame(GhnActionOutcome::STATUS_UNKNOWN_RESULT, $outcome->getStatus());
    }

    public function test429YieldsTechnicalFailure(): void
    {
        // r2: a 429 throttle rejected the request BEFORE processing — definitively not applied.
        $this->apiClient->method('post')->willThrowException(
            new ProviderRateLimitException(new \Magento\Framework\Phrase('too many requests'))
        );

        $outcome = $this->service->cancel($this->shipment(), 'GHN-CO003');

        $this->assertSame(GhnActionOutcome::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        $this->assertSame('TECHNICAL_ERROR', $outcome->getReasonCode());
    }

    public function testAuthFailureIsBusinessRejection(): void
    {
        $this->apiClient->method('post')->willThrowException(
            new ProviderAuthenticationException(new \Magento\Framework\Phrase('bad token'))
        );

        $outcome = $this->service->cancel($this->shipment(), 'GHN-CO003');

        $this->assertSame(GhnActionOutcome::STATUS_BUSINESS_REJECTED, $outcome->getStatus());
    }

    // ---------- helpers ----------

    private function shipment(): Shipment&MockObject
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getEntityId')->willReturn(10);
        $shipment->method('getOrderId')->willReturn(15);
        $shipment->method('getStoreId')->willReturn(1);

        return $shipment;
    }
}
