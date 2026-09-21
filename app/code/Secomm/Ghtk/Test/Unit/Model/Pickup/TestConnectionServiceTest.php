<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Pickup;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\Ghtk\Model\Pickup\TestConnectionResult;
use Secomm\Ghtk\Model\Pickup\TestConnectionService;
use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;

/**
 * TASK-3HPB76 — Test Connection matrix (§41): connectivity/auth split from
 * configured pickup-id exact validation; no configured ID is still CONNECTED;
 * a missing configured ID fails loudly with NO first-pickup fallback.
 */
class TestConnectionServiceTest extends TestCase
{
    private GhtkApiClient&MockObject $apiClient;
    private GhtkConfig&MockObject $config;
    private TestConnectionService $service;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(GhtkApiClient::class);
        $this->config = $this->createMock(GhtkConfig::class);
        $this->service = new TestConnectionService($this->apiClient, $this->config);
    }

    private function configured(string $id): void
    {
        $this->config->method('getPickAddressId')->willReturn($id);
    }

    private function noConfiguredPickupId(): void
    {
        $this->configured('');
    }

    private function pickupRow(string $id, string $name = 'Store 1'): array
    {
        return ['pick_address_id' => $id, 'pick_name' => $name, 'pick_tel' => '0900000000', 'address' => 'Fake address 1'];
    }

    private function listResponse(array $rows): array
    {
        return ['success' => true, 'message' => '', 'data' => $rows];
    }

    private function transportException(string $category): GhtkApiException
    {
        return new GhtkApiException('transport failed', false, 0, null, $category);
    }

    // ------------------------------------------------------------ §41 matrix

    public function testValidApiWithNoConfiguredPickupIdIsConnected(): void
    {
        $this->noConfiguredPickupId();
        $this->apiClient->expects($this->once())->method('getPickupAddresses')
            ->willReturn($this->listResponse([$this->pickupRow('88256')]));
        $this->config->expects($this->once())->method('getPickAddressId');

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::CONNECTED, $result->getStatus());
        $this->assertTrue($result->isConnectionOk());
        $this->assertNull($result->getConfiguredPickupId());
        $this->assertSame(1, $result->getPickupCount());
        $this->assertStringContainsString('No pickup address ID configured', $result->getMessage());
    }

    public function testValidApiWithValidConfiguredPickupIdIsConnectedAndNamed(): void
    {
        $this->configured('88256');
        $this->apiClient->method('getPickupAddresses')
            ->willReturn($this->listResponse([$this->pickupRow('88256', 'Store 1')]));

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::CONNECTED, $result->getStatus());
        $this->assertSame('88256', $result->getConfiguredPickupId());
        $this->assertSame('Store 1', $result->getConfiguredPickupName());
        $this->assertStringContainsString('valid', $result->getMessage());
    }

    public function testValidApiWithInvalidConfiguredPickupIdFailsLoudly(): void
    {
        $this->configured('99999');
        $this->apiClient->method('getPickupAddresses')
            ->willReturn($this->listResponse([$this->pickupRow('88256'), $this->pickupRow('88257')]));

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::PICKUP_ID_INVALID, $result->getStatus());
        $this->assertFalse($result->isConnectionOk());
        $this->assertStringContainsString('was not found in the merchant pickup list', $result->getMessage());
        $this->assertSame(2, $result->getPickupCount());
    }

    public function testInvalidConfiguredIdNeverFallsBackToFirstPickup(): void
    {
        // §14 — the first returned pickup is NEVER silently adopted.
        $this->configured('99999');
        $this->apiClient->method('getPickupAddresses')
            ->willReturn($this->listResponse([$this->pickupRow('88256', 'First Store')]));

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::PICKUP_ID_INVALID, $result->getStatus());
        $this->assertNull($result->getConfiguredPickupName());
    }

    public function testEmptyPickupListWithNoConfiguredIdIsStillConnected(): void
    {
        $this->noConfiguredPickupId();
        $this->apiClient->method('getPickupAddresses')->willReturn($this->listResponse([]));

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::CONNECTED, $result->getStatus());
        $this->assertSame(0, $result->getPickupCount());
    }

    public function testLegacyTextOnlyConfigWithNoIdStillPassesConnection(): void
    {
        // §15 — legacy text pickup config must not fail the connection test for a
        // missing ID (no pick_address_id configured at all).
        $this->apiClient->method('getPickupAddresses')->willReturn($this->listResponse([$this->pickupRow('88256')]));
        $this->config->method('getPickAddressId')->willReturn('');

        $result = $this->service->test(null);

        $this->assertTrue($result->isConnectionOk());
    }

    // ------------------------------------------------------------ failures

    public function testClientErrorCategoryYieldsAuthFailed(): void
    {
        // 403 invalid/expired token (empty body) — merchant auth/config, not network.
        $this->noConfiguredPickupId();
        $this->apiClient->method('getPickupAddresses')
            ->willThrowException($this->transportException(CarrierHttpErrorCategory::CLIENT_ERROR));

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::AUTH_FAILED, $result->getStatus());
        $this->assertStringContainsString('authentication failed', $result->getMessage());
    }

    public function testNetworkCategoryYieldsTechnicalFailure(): void
    {
        $this->noConfiguredPickupId();
        $this->apiClient->method('getPickupAddresses')
            ->willThrowException($this->transportException(CarrierHttpErrorCategory::NETWORK));

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::TECHNICAL_FAILURE, $result->getStatus());
    }

    public function testServerErrorCategoryYieldsTechnicalFailure(): void
    {
        $this->noConfiguredPickupId();
        $this->apiClient->method('getPickupAddresses')
            ->willThrowException($this->transportException(CarrierHttpErrorCategory::SERVER_ERROR));

        $this->assertSame(TestConnectionResult::TECHNICAL_FAILURE, $this->service->test(null)->getStatus());
    }

    public function testTimeoutCategoryYieldsTechnicalFailure(): void
    {
        $this->noConfiguredPickupId();
        $this->apiClient->method('getPickupAddresses')
            ->willThrowException($this->transportException(CarrierHttpErrorCategory::TIMEOUT));

        $this->assertSame(TestConnectionResult::TECHNICAL_FAILURE, $this->service->test(null)->getStatus());
    }

    public function testSuccessFalseResponseIsTechnicalUnusable(): void
    {
        // HTTP 200 but success=false on a read-only list call — unusable technical
        // data for connectivity purposes (no documented business semantics here).
        $this->noConfiguredPickupId();
        $this->apiClient->method('getPickupAddresses')
            ->willReturn(['success' => false, 'message' => 'unexpected']);

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::TECHNICAL_FAILURE, $result->getStatus());
    }

    public function testNonArrayDataIsTechnicalUnusable(): void
    {
        $this->noConfiguredPickupId();
        $this->apiClient->method('getPickupAddresses')
            ->willReturn(['success' => true, 'data' => 'not-a-list']);

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::TECHNICAL_FAILURE, $this->service->test(null)->getStatus());
    }

    public function testMalformedRowsAreSkippedAndValidRowsStillCount(): void
    {
        $this->noConfiguredPickupId();
        $this->apiClient->method('getPickupAddresses')->willReturn($this->listResponse([
            ['pick_name' => 'no id row'],               // skipped
            'garbage-string-row',                        // skipped
            $this->pickupRow('88256', 'Valid Store'),
        ]));

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::CONNECTED, $result->getStatus());
        $this->assertSame(1, $result->getPickupCount());
    }

    public function testConfiguredIdMatchesOnlyExactString(): void
    {
        // Exact-ID validation — no fuzzy/prefix/numeric coercion.
        $this->configured('8825');
        $this->apiClient->method('getPickupAddresses')->willReturn($this->listResponse([
            $this->pickupRow('88256'),
        ]));

        $result = $this->service->test(null);

        $this->assertSame(TestConnectionResult::PICKUP_ID_INVALID, $result->getStatus());
    }

    public function testStoreIdFlowsToTheClient(): void
    {
        $this->apiClient->expects($this->once())->method('getPickupAddresses')->with(3)
            ->willReturn($this->listResponse([]));

        $this->service->test(3);
    }
}
