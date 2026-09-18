<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Gateway\Http\Client;

use Laminas\Http\Response as LaminasResponse;
use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\HTTP\LaminasClientFactory;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger as PaymentMethodLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Gateway\Http\Client\Zend;
use Secomm\ZaloPay\Gateway\Http\Converter\JsonToArray;

/**
 * UNIT - ZaloPay HTTP client debug logging contract (TASK-MCHN2T feature B).
 *
 * Zend always hands its payload to the core payment method logger; the
 * Debug Mode gate (`payment/zalopay/debug`, default OFF) lives INSIDE
 * Magento\Payment\Model\Method\Logger (AC6: OFF -> nothing reaches Monolog;
 * real error/critical logging is a different path and stays on). When it
 * writes, the maskKeys handed over make the core filter scrub sensitive
 * keys recursively - including the nested response payload (AC7), which
 * previously would have been logged raw.
 */
class ZendTest extends TestCase
{
    private LaminasClientFactory|MockObject $clientFactory;

    private PaymentMethodLogger|MockObject $paymentLogger;

    private JsonToArray|MockObject $converter;

    private LaminasClient|MockObject $client;


    protected function setUp(): void
    {
        $this->clientFactory = $this->createMock(LaminasClientFactory::class);
        $this->paymentLogger = $this->createMock(PaymentMethodLogger::class);
        $this->converter = $this->createMock(JsonToArray::class);
        $this->client = $this->getMockBuilder(\Magento\Framework\HTTP\LaminasClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setOptions', 'setMethod', 'setParameterPost', 'setHeaders', 'setUrlEncodeBody', 'setUri', 'send'])
            ->getMock();
        $this->clientFactory->method('create')->willReturn($this->client);
        $this->client->method('send')->willReturn($this->createMock(LaminasResponse::class));
    }

    /**
     * AC6 - the gate itself: the core payment method logger configured like
     * the module's ZaloPayLogger virtual type drops everything when the
     * Admin Debug Mode flag is OFF. Nothing reaches Monolog.
     */
    public function testDebugModeOffSuppressesProviderPayloadLogging(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('getValue')->with('debug')->willReturn(0);
        $monolog = $this->createMock(LoggerInterface::class);
        $monolog->expects($this->never())->method('debug');

        $logger = new PaymentMethodLogger($monolog, $config);
        $logger->debug(
            ['request' => ['app_id' => '1', 'key1' => 'SECRET'], 'response' => ['key1' => 'SECRET']],
            ['key1']
        );
    }

    /**
     * AC7 - with Debug Mode ON the core logger writes ONE masked record and
     * the maskKeys handed over by Zend scrub the nested response payload too.
     */
    public function testDebugModeOnWritesMaskedRecord(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('getValue')->with('debug')->willReturn(1);
        $monolog = $this->createMock(LoggerInterface::class);
        $written = null;
        $monolog->expects($this->once())->method('debug')->willReturnCallback(
            function (string $logLine) use (&$written) {
                $written = $logLine;
            }
        );

        $logger = new PaymentMethodLogger($monolog, $config);
        $logger->debug(
            [
                'request' => ['app_id' => '1', 'key1' => 'RAW-KEY1'],
                'response' => ['return_code' => 1, 'data' => ['key1' => 'RAW-KEY1', 'mac' => 'RAW-MAC']],
            ],
            ['key1', 'mac']
        );

        $this->assertIsString($written);
        $this->assertStringNotContainsString('RAW-KEY1', $written);
        $this->assertStringNotContainsString('RAW-MAC', $written);
        $this->assertStringContainsString("'app_id' => '1'", $written);
    }

    /**
     * Zend hands the payment method logger the FULL payload (pre-masked
     * request body) plus its sensitive key list, so the core recursive
     * filter covers the response too. key1 (previously unmasked!) is on
     * the list next to key2 and mac.
     */
    public function testPlaceRequestHandsPayloadAndSensitiveKeysToMethodLogger(): void
    {
        $this->converter->method('convert')->willReturn(
            ['return_code' => 1, 'return_message' => 'ok', 'data' => ['key1' => 'RAW-KEY1']]
        );
        $response = $this->createMock(LaminasResponse::class);
        $response->method('getBody')->willReturn('{"return_code":1}');
        $this->client->method('send')->willReturn($response);

        $capturedLog = null;
        $capturedMaskKeys = null;
        $this->paymentLogger->expects($this->once())->method('debug')->willReturnCallback(
            function (array $log, ?array $maskKeys) use (&$capturedLog, &$capturedMaskKeys) {
                $capturedLog = $log;
                $capturedMaskKeys = $maskKeys;
            }
        );

        $client = new Zend(
            $this->clientFactory,
            $this->paymentLogger,
            $this->converter
        );
        $client->placeRequest($this->transferStub());

        // The core logger masks recursively with THESE keys (incl. response).
        $this->assertNotNull($capturedMaskKeys);
        $this->assertEqualsCanonicalizing(
            ['mac', 'signature', 'hmac', 'secret', 'secretkey', 'key1', 'key2', 'access_key', 'secret_key'],
            $capturedMaskKeys
        );
        // Request body is pre-masked (defense in depth).
        $this->assertSame('****', $capturedLog['request']['key1']);
        $this->assertSame('****', $capturedLog['request']['key2']);
        $this->assertSame('****', $capturedLog['request']['mac']);
        $this->assertSame('1004', $capturedLog['request']['app_id']);
        $this->assertStringContainsString('zalopay', $capturedLog['request_uri']);
    }

    /**
     * @return TransferInterface|MockObject
     */
    private function transferStub(): TransferInterface|MockObject
    {
        $transfer = $this->createMock(TransferInterface::class);
        $transfer->method('getBody')->willReturn(
            ['app_id' => '1004', 'key1' => 'SECRET-KEY1', 'key2' => 'SECRET-KEY2', 'mac' => 'SECRET-MAC']
        );
        $transfer->method('getUri')->willReturn('https://sb-openapi.zalopay.vn/v2/query');
        $transfer->method('getClientConfig')->willReturn(['timeout' => 15]);
        $transfer->method('getMethod')->willReturn(\Laminas\Http\Request::METHOD_POST);
        $transfer->method('getHeaders')->willReturn([]);
        $transfer->method('shouldEncode')->willReturn(true);

        return $transfer;
    }
}
