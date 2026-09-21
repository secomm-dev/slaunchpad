<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Http;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Http\CarrierHttpException;
use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;
use Secomm\ShippingCore\Model\Http\CarrierHttpRequest;
use Secomm\ShippingCore\Model\Http\CurlCarrierHttpClient;

/**
 * TASK-7AJ3K8 — shared transport: ONE exchange per call, every failure a categorized throw.
 */
class CurlCarrierHttpClientTest extends TestCase
{
    private Curl&MockObject $curl;
    private CurlFactory&MockObject $curlFactory;
    private CurlCarrierHttpClient $client;

    protected function setUp(): void
    {
        $this->curl = $this->createMock(Curl::class);
        $this->curlFactory = $this->createMock(CurlFactory::class);
        $this->curlFactory->method('create')->willReturn($this->curl);
        $this->client = new CurlCarrierHttpClient($this->curlFactory);
    }

    private function request(string $method = CarrierHttpRequest::METHOD_GET, ?string $body = null): CarrierHttpRequest
    {
        return new CarrierHttpRequest(
            method: $method,
            uri: 'https://carrier.example/api?x=1',
            headers: ['Token' => 'secret'],
            body: $body,
            timeoutConnect: 2,
            timeoutTotal: 5
        );
    }

    public function testGetAppliesTimeoutsHeadersAndReturnsResponse(): void
    {
        $this->curl->expects($this->once())->method('setOptions')->with($this->callback(
            fn (array $options): bool => $options[CURLOPT_CONNECTTIMEOUT] === 2 && $options[CURLOPT_TIMEOUT] === 5
        ));
        $this->curl->expects($this->once())->method('addHeader')->with('Token', 'secret');
        $this->curl->expects($this->once())->method('get')->with('https://carrier.example/api?x=1');
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"ok":true}');

        $response = $this->client->send($this->request());

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('{"ok":true}', $response->getBody());
    }

    public function testPostSendsTheBody(): void
    {
        $this->curl->expects($this->once())->method('post')
            ->with('https://carrier.example/api?x=1', '{"a":1}');
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{}');

        $this->client->send($this->request(CarrierHttpRequest::METHOD_POST, '{"a":1}'));
    }

    public function testZeroStatusIsClassifiedNetwork(): void
    {
        $this->curl->method('getStatus')->willReturn(0);

        try {
            $this->client->send($this->request());
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException $e) {
            $this->assertSame(CarrierHttpErrorCategory::NETWORK, $e->getCategory());
        }
    }

    public function testCurlExceptionIsClassifiedNetwork(): void
    {
        $this->curl->method('get')->willThrowException(new \RuntimeException('connection reset'));

        try {
            $this->client->send($this->request());
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException $e) {
            $this->assertSame(CarrierHttpErrorCategory::NETWORK, $e->getCategory());
            $this->assertSame('connection reset', $e->getPrevious()?->getMessage());
        }
    }

    public function test5xxIsClassifiedServerError(): void
    {
        $this->curl->method('getStatus')->willReturn(503);

        try {
            $this->client->send($this->request());
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException $e) {
            $this->assertSame(CarrierHttpErrorCategory::SERVER_ERROR, $e->getCategory());
        }
    }

    public function test429IsClassifiedRateLimit(): void
    {
        $this->curl->method('getStatus')->willReturn(429);

        try {
            $this->client->send($this->request());
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException $e) {
            $this->assertSame(CarrierHttpErrorCategory::RATE_LIMIT, $e->getCategory());
        }
    }

    public function testOther4xxIsClassifiedClientError(): void
    {
        $this->curl->method('getStatus')->willReturn(422);

        try {
            $this->client->send($this->request());
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException $e) {
            $this->assertSame(CarrierHttpErrorCategory::CLIENT_ERROR, $e->getCategory());
        }
    }

    public function testInvalidJsonIsClassifiedInvalidResponse(): void
    {
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('<html>not json</html>');

        try {
            $this->client->sendJson($this->request());
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException $e) {
            $this->assertSame(CarrierHttpErrorCategory::INVALID_RESPONSE, $e->getCategory());
        }
    }

    public function testScalarJsonIsAlsoInvalid(): void
    {
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('"just a string"');

        try {
            $this->client->sendJson($this->request());
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException $e) {
            $this->assertSame(CarrierHttpErrorCategory::INVALID_RESPONSE, $e->getCategory());
        }
    }

    public function testSendJsonDecodesObjectResponses(): void
    {
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"success":true,"fee":25000}');

        $this->assertSame(['success' => true, 'fee' => 25000], $this->client->sendJson($this->request()));
    }
}
