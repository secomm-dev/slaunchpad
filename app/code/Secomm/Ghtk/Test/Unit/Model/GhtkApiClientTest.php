<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\Ghtk\Model\GhtkApiProfile;
use Secomm\Ghtk\Model\Log\MaskingLogger;
use Secomm\ShippingCore\Api\Http\CarrierHttpException;
use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;
use Secomm\ShippingCore\Api\Http\CarrierHttpClientInterface;
use Secomm\ShippingCore\Model\Http\CarrierHttpRequest;
use Secomm\ShippingCore\Model\Http\RetryExecutor;

/**
 * TASK-7AJ3K8 — GHTK client on the SHARED transport: safe reads retry (config retry_max,
 * NETWORK/SERVER_ERROR only), create-order is SINGLE attempt (DEC-SL016-001 — never changed),
 * 4xx/429/invalid JSON never retry, and the carrier-facing GhtkApiException surface is kept.
 */
class GhtkApiClientTest extends TestCase
{
    private CarrierHttpClientInterface&MockObject $httpClient;
    private GhtkConfig&MockObject $config;
    private GhtkApiClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(CarrierHttpClientInterface::class);
        $this->config = $this->createMock(GhtkConfig::class);
        $this->config->method('getApiBaseUrl')->willReturn('https://services.giaohangtietkiem.vn');
        $this->config->method('getRetryMax')->willReturn(1);
        $this->config->method('getTimeoutConnect')->willReturn(2);
        $this->config->method('getTimeoutTotal')->willReturn(5);
        $this->config->method('getApiToken')->willReturn('tok');
        $this->config->method('getClientSource')->willReturn('slp');

        $this->client = new GhtkApiClient(
            $this->httpClient,
            new RetryExecutor(),
            $this->config,
            new GhtkApiProfile(),
            $this->createMock(MaskingLogger::class)
        );
    }

    public function testGetFeeBuildsTheProfileFeeUriWithQueryAndAuthHeaders(): void
    {
        $captured = null;
        $this->httpClient->expects($this->once())->method('sendJson')
            ->willReturnCallback(function (CarrierHttpRequest $request) use (&$captured): array {
                $captured = $request;

                return ['success' => true];
            });

        $result = $this->client->getFee(['province' => 'Hà Nội', 'weight' => 1000]);

        $this->assertSame(['success' => true], $result);
        $this->assertNotNull($captured);
        self::assertSame(CarrierHttpRequest::METHOD_GET, $captured->getMethod());
        self::assertSame(
            'https://services.giaohangtietkiem.vn/services/shipment/fee?province=H%C3%A0+N%E1%BB%99i&weight=1000',
            $captured->getUri()
        );
        self::assertSame('tok', $captured->getHeaders()['Token'] ?? null);
        self::assertSame('slp', $captured->getHeaders()['X-Client-Source'] ?? null);
        self::assertArrayNotHasKey('Content-Type', $captured->getHeaders());
    }

    public function testGetFeeRetriesServerErrorUpToRetryMax(): void
    {
        $calls = 0;
        $this->httpClient->expects($this->exactly(2))->method('sendJson')
            ->willReturnCallback(function () use (&$calls): array {
                $calls++;
                if ($calls === 1) {
                    throw new CarrierHttpException(CarrierHttpErrorCategory::SERVER_ERROR, 'boom');
                }

                return ['success' => true];
            });

        $this->assertSame(['success' => true], $this->client->getFee(['weight' => 1]));
    }

    public function testGetFeeExhaustedRetriesThrowRetryableException(): void
    {
        $this->httpClient->method('sendJson')
            ->willThrowException(new CarrierHttpException(CarrierHttpErrorCategory::NETWORK, 'down'));

        try {
            $this->client->getFee(['weight' => 1]);
            self::fail('Expected GhtkApiException');
        } catch (GhtkApiException $e) {
            self::assertTrue($e->isRetryable());
        }
    }

    public function testGetFeeNeverRetriesClientErrors(): void
    {
        $this->httpClient->expects($this->once())->method('sendJson')
            ->willThrowException(new CarrierHttpException(CarrierHttpErrorCategory::CLIENT_ERROR, 'bad request'));

        try {
            $this->client->getFee(['weight' => 1]);
            self::fail('Expected GhtkApiException');
        } catch (GhtkApiException $e) {
            self::assertFalse($e->isRetryable());
        }
    }

    public function testGetFeeNeverRetriesRateLimitOrInvalidJson(): void
    {
        foreach ([CarrierHttpErrorCategory::RATE_LIMIT, CarrierHttpErrorCategory::INVALID_RESPONSE] as $category) {
            $client = $this->createMock(CarrierHttpClientInterface::class);
            $client->expects($this->once())->method('sendJson')
                ->willThrowException(new CarrierHttpException($category, $category));
            $oneShot = new GhtkApiClient(
                $client,
                new RetryExecutor(),
                $this->config,
                new GhtkApiProfile(),
                $this->createMock(MaskingLogger::class)
            );

            try {
                $oneShot->getFee(['weight' => 1]);
                self::fail('Expected GhtkApiException for ' . $category);
            } catch (GhtkApiException $e) {
                self::assertFalse($e->isRetryable());
            }
        }
    }

    public function testSubmitOrderIsASingleAttemptNeverRetried(): void
    {
        $calls = 0;
        $this->httpClient->expects($this->once())->method('sendJson')
            ->willReturnCallback(function (CarrierHttpRequest $request) use (&$calls): array {
                $calls++;
                throw new CarrierHttpException(CarrierHttpErrorCategory::SERVER_ERROR, 'flaky 500');
            });

        try {
            $this->client->submitOrder(['partner_order_id' => 'ghtk-1-1']);
            self::fail('Expected GhtkApiException');
        } catch (GhtkApiException $e) {
            // retryable flag stays true (legacy surface semantics) but NOTHING consumes it:
            // the create path never loops — duplicate orders are impossible by construction.
            self::assertSame(1, $calls);
        }
    }

    public function testSubmitOrderSendsJsonBodyWithContentTypeAndProfilePath(): void
    {
        $captured = null;
        $this->httpClient->expects($this->once())->method('sendJson')
            ->willReturnCallback(function (CarrierHttpRequest $request) use (&$captured): array {
                $captured = $request;

                return ['success' => true];
            });

        $this->client->submitOrder(['partner_order_id' => 'ghtk-1-1', 'weight' => 1000]);

        self::assertSame(CarrierHttpRequest::METHOD_POST, $captured->getMethod());
        self::assertSame('https://services.giaohangtietkiem.vn/services/shipment/order', $captured->getUri());
        self::assertSame('application/json', $captured->getHeaders()['Content-Type'] ?? null);
        self::assertSame('{"partner_order_id":"ghtk-1-1","weight":1000}', $captured->getBody());
    }

    public function testGetPickupAddressesUsesTheProfilePickupPathWithSafeReadRetry(): void
    {
        $calls = 0;
        $captured = null;
        $this->httpClient->expects($this->exactly(2))->method('sendJson')
            ->willReturnCallback(function (CarrierHttpRequest $request) use (&$calls, &$captured): array {
                $calls++;
                $captured = $request;
                if ($calls === 1) {
                    throw new CarrierHttpException(CarrierHttpErrorCategory::SERVER_ERROR, 'flaky');
                }

                return ['success' => true, 'data' => []];
            });

        $result = $this->client->getPickupAddresses();

        // Read-only operation — approved safe-read retry applies.
        $this->assertSame(['success' => true, 'data' => []], $result);
        self::assertSame(CarrierHttpRequest::METHOD_GET, $captured->getMethod());
        self::assertSame('https://services.giaohangtietkiem.vn/services/shipment/list_pick_add', $captured->getUri());
    }

    public function testGetPickupAddressesNeverRetriesClientErrors(): void
    {
        $this->httpClient->expects($this->once())->method('sendJson')
            ->willThrowException(new CarrierHttpException(CarrierHttpErrorCategory::CLIENT_ERROR, 'status 403'));

        try {
            $this->client->getPickupAddresses();
            self::fail('Expected GhtkApiException');
        } catch (GhtkApiException $e) {
            self::assertFalse($e->isRetryable());
        }
    }

    public function testGetOrderStatusUsesTheProfileStatusPath(): void
    {
        $captured = null;
        $this->httpClient->expects($this->once())->method('sendJson')
            ->willReturnCallback(function (CarrierHttpRequest $request) use (&$captured): array {
                $captured = $request;

                return ['order' => ['status' => 5]];
            });

        $this->client->getOrderStatus('GHTK 123');

        self::assertSame(
            'https://services.giaohangtietkiem.vn/services/shipment/v2/GHTK%20123',
            $captured->getUri()
        );
    }
}
