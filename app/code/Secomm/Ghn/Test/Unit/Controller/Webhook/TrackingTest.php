<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Controller\Webhook;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ghn\Controller\Webhook\Tracking;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Tracking\WebhookPayloadParser;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingProcessorInterface;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * TASK-GKHXY1 (GHN-E1) — webhook endpoint containment: secret fail-closed, always-JSON responses,
 * provider-compatible ack for unprocessable payloads, 500 (GHN retry) only for internal errors —
 * and NEVER any Magento order business mutation from the lifecycle path.
 *
 * Stub discipline: PHPUnit keeps the FIRST stub per method — every request/config stub is
 * property-driven so tests mutate values, not registrations.
 */
class TrackingTest extends TestCase
{
    private Config&MockObject $config;

    private WebhookPayloadParser&MockObject $parser;

    private CarrierTrackingProcessorInterface&MockObject $processor;

    private LoggerInterface&MockObject $psrLogger;

    private Http&MockObject $request;

    private ResultFactory&MockObject $resultFactory;

    /** Values the property-driven stubs read at invocation time. */
    private string $secretHeaderValue = 'qc-secret';

    private string $configuredSecret = 'qc-secret';

    private string $content = '{}';

    private ?array $posted = null;

    private Tracking $controller;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->parser = $this->createMock(WebhookPayloadParser::class);
        $this->processor = $this->createMock(CarrierTrackingProcessorInterface::class);
        $this->psrLogger = $this->createMock(LoggerInterface::class);
        $this->request = $this->createMock(Http::class);
        // GhnLogger is final and NOT PSR — the controller type-hints the concrete wrapper;
        // expectations target the underlying PSR logger the wrapper delegates to.
        $this->resultFactory = $this->createMock(ResultFactory::class);

        $headerValue = &$this->secretHeaderValue;
        $this->request->method('getHeader')->willReturnCallback(
            function (string $name) use (&$headerValue): string {
                return $name === Tracking::SECRET_HEADER ? $headerValue : '';
            }
        );
        $content = &$this->content;
        $this->request->method('getContent')->willReturnCallback(
            function () use (&$content): string {
                return $content;
            }
        );
        $posted = &$this->posted;
        $this->request->method('getParam')->willReturnCallback(
            function (string $key) use (&$posted): ?array {
                return $key === 'shipment' ? $posted : null;
            }
        );

        $configuredSecret = &$this->configuredSecret;
        $this->config->method('getWebhookSecret')->willReturnCallback(
            function () use (&$configuredSecret): string {
                return $configuredSecret;
            }
        );

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $this->controller = new Tracking(
            $context,
            $this->parser,
            $this->processor,
            $this->config,
            new GhnLogger($this->psrLogger)
        );
        // Inject the result factory (parent Action resolves it through the object manager).
        $reflection = new \ReflectionProperty(\Magento\Framework\App\Action\Action::class, 'resultFactory');
        $reflection->setAccessible(true);
        $reflection->setValue($this->controller, $this->resultFactory);
    }

    private function expectJson(array $data, int $code = 200): Json&MockObject
    {
        $result = $this->createMock(Json::class);
        $result->expects($this->once())->method('setHttpResponseCode')->with($code);
        $result->expects($this->once())->method('setData')->with($data);

        $this->resultFactory->method('create')->willReturn($result);

        return $result;
    }

    public function testWrongSecretIsRejected401WithoutProcessing(): void
    {
        $this->secretHeaderValue = 'wrong';
        $this->processor->expects($this->never())->method('process');
        $expected = ['ok' => false, 'error' => 'invalid_secret'];
        $result = $this->createMock(Json::class);
        $result->method('setHttpResponseCode')->with(401)->willReturnSelf();
        $result->method('setData')->with($expected)->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }

    public function testMissingSecretConfigurationFailsClosed401(): void
    {
        $this->configuredSecret = '';
        $this->processor->expects($this->never())->method('process');
        $expected = ['ok' => false, 'error' => 'invalid_secret'];
        $result = $this->createMock(Json::class);
        $result->method('setHttpResponseCode')->with(401)->willReturnSelf();
        $result->method('setData')->with($expected)->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }

    public function testUnprocessablePayloadIsAcknowledgedAndDropped(): void
    {
        $this->parser->method('parse')->willReturn(null);
        $result = $this->createMock(Json::class);
        $result->method('setHttpResponseCode')->with(200)->willReturnSelf();
        $result->method('setData')->with(['ok' => false, 'error' => 'invalid_payload'])->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }

    public function testDocumentedNonLifecycleTypeIsAckedUnsupportedEvent(): void
    {
        // structurally valid, but E1 only consumes switch_status lifecycle transitions
        $this->psrLogger->method('error')->willReturnCallback(function (string $m, array $c): void {
            fwrite(STDERR, "DBG: $m | " . substr($c['exception'] ?? '', 0, 300) . "\n");
        });
        $this->content = '{"OrderCode":"L8TKYG","Type":"update_cod","Status":"","Time":"2026-09-15 12:00:00"}';
        $this->parser->expects($this->never())->method('parse');
        $result = $this->createMock(Json::class);
        $result->method('setHttpResponseCode')->with(200)->willReturnSelf();
        $result->method('setData')->with(['ok' => true, 'matched' => false, 'error' => 'unsupported_event_type'])->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }

    public function testUnknownEventTypeIsAckedUnsupportedEvent(): void
    {
        $this->content = '{"OrderCode":"L8TKYG","Type":"martian_event","Status":"delivering","Time":"2026-09-15 12:00:00"}';
        $this->parser->expects($this->never())->method('parse');
        $result = $this->createMock(Json::class);
        $result->method('setHttpResponseCode')->with(200)->willReturnSelf();
        $result->method('setData')->with(['ok' => true, 'matched' => false, 'error' => 'unsupported_event_type'])->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }

    public function testMalformedJsonBodyIsAcknowledgedAndDropped(): void
    {
        $this->content = 'not-json{';
        // json_decode('not-json{') → null → shape gate rejects BEFORE the parser runs.
        $this->parser->expects($this->never())->method('parse');
        $result = $this->createMock(Json::class);
        $result->method('setHttpResponseCode')->with(200)->willReturnSelf();
        $result->method('setData')->with(['ok' => false, 'error' => 'invalid_payload'])->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }

    public function testMatchedUpdateReturnsOkTrue(): void
    {
        $this->parser->method('parse')->willReturn(
            new TrackingUpdate('secomm_ghn', 'L8TKYG', 'OUT_FOR_DELIVERY', 'delivering', null, null, 'webhook', [])
        );
        $this->processor->expects($this->once())->method('process')->willReturn(true);
        $result = $this->createMock(Json::class);
        $result->method('setHttpResponseCode')->with(200)->willReturnSelf();
        $result->method('setData')->with(['ok' => true, 'matched' => true])->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }

    public function testInternalErrorReturns500ForProviderRetry(): void
    {
        $this->parser->method('parse')->willThrowException(new \RuntimeException('db down'));
        $this->psrLogger->expects($this->once())->method('error')->with(
            'GHN webhook failed unexpectedly.',
            $this->callback(fn (array $context): bool => $context['exception'] === 'db down')
        );
        $result = $this->createMock(Json::class);
        $result->method('setHttpResponseCode')->with(500)->willReturnSelf();
        $result->method('setData')->with(['ok' => false, 'error' => 'internal_error'])->willReturnSelf();
        $this->resultFactory->method('create')->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }

    public function testCsrfIsExemptedByDesign(): void
    {
        $request = $this->createMock(Http::class);
        $this->assertTrue($this->controller->validateForCsrf($request));
        $this->assertNull($this->controller->createCsrfValidationException($request));
    }
}
