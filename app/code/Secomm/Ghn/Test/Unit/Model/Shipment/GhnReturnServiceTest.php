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
use Secomm\Ghn\Api\Exception\ProviderRateLimitException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Shipment\GhnActionOutcome;
use Secomm\Ghn\Model\Shipment\GhnReturnService;
use Secomm\Ghn\Model\Shipment\GhnShipmentRepository;
use Secomm\Ghn\Model\Logger\GhnLogger;

/**
 * TASK-4ATBC4 (GHN-E2) — the RETURN action contract (force R2S): order_codes-only payload,
 * per-order best-effort result (HTTP 200 + result:false is NEVER success), repeat-safe
 * normalization, mutation uncertainty → UNKNOWN_RESULT (no blind retry).
 */
class GhnReturnServiceTest extends TestCase
{
    private GhnApiClientInterface&MockObject $apiClient;

    private GhnShipmentRepository&MockObject $repository;

    private GhnReturnService $service;

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
        $this->service = new GhnReturnService(
            $this->apiClient,
            $this->repository,
            new GhnLogger($this->createMock(\Psr\Log\LoggerInterface::class))
        );
    }

    public function testSuccessfulProviderResultIsSuccess(): void
    {
        $this->apiClient->expects($this->once())->method('post')->with(
            'return_order',
            'v2/switch-status/return',
            ['order_codes' => ['L8TKYG']]
        )->willReturn([
            ['order_code' => 'L8TKYG', 'result' => true, 'message' => 'OK'],
        ]);

        $outcome = $this->service->requestReturn($this->shipment());

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(GhnActionOutcome::STATUS_SUCCESS, $outcome->getStatus());
        $this->assertSame('L8TKYG', $outcome->getProviderOrderCode());
    }

    public function testProviderRefusalIsBusinessRejected(): void
    {
        $this->apiClient->method('post')->willReturn([
            ['order_code' => 'L8TKYG', 'result' => false, 'message' => 'Trạng thái không cho phép return'],
        ]);

        $outcome = $this->service->requestReturn($this->shipment());

        $this->assertFalse($outcome->isSuccessful());
        $this->assertSame(GhnActionOutcome::STATUS_BUSINESS_REJECTED, $outcome->getStatus());
    }

    public function testMissingProviderOrderFailsClosed(): void
    {
        $this->existingRow = null;
        $this->apiClient->expects($this->never())->method('post');

        $outcome = $this->service->requestReturn($this->shipment());

        $this->assertSame(GhnActionOutcome::STATUS_BUSINESS_REJECTED, $outcome->getStatus());
        $this->assertSame('NO_PROVIDER_ORDER', $outcome->getReasonCode());
    }

    public function testTimeoutYieldsUnknownResultForReconciliation(): void
    {
        $this->apiClient->method('post')->willThrowException(
            new ProviderTimeoutException(new \Magento\Framework\Phrase('timed out'))
        );

        $outcome = $this->service->requestReturn($this->shipment());

        $this->assertSame(GhnActionOutcome::STATUS_UNKNOWN_RESULT, $outcome->getStatus());
    }

    public function test5xxYieldsUnknownResult(): void
    {
        // TASK-GKHXY1 r2: a 5xx leaves applied-vs-not-applied open — never a definitive failure.
        $this->apiClient->method('post')->willThrowException(
            new ProviderServiceUnavailableException(new \Magento\Framework\Phrase('SERVER_ERROR'))
        );

        $outcome = $this->service->requestReturn($this->shipment());

        $this->assertSame(GhnActionOutcome::STATUS_UNKNOWN_RESULT, $outcome->getStatus());
    }

    public function test429YieldsTechnicalFailure(): void
    {
        // r2: a 429 throttle rejected the request BEFORE processing — definitively not applied.
        $this->apiClient->method('post')->willThrowException(
            new ProviderRateLimitException(new \Magento\Framework\Phrase('too many requests'))
        );

        $outcome = $this->service->requestReturn($this->shipment());

        $this->assertSame(GhnActionOutcome::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        $this->assertSame('TECHNICAL_ERROR', $outcome->getReasonCode());
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
