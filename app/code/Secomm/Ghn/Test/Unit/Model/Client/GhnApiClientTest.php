<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Client;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderInvalidRequestException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Client\GhnApiClient;
use Secomm\Ghn\Model\Client\GhnEndpoints;
use Secomm\Ghn\Model\Client\GhnErrorTranslator;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Logger\GhnLogger;

/**
 * TASK-RJFTPZ / AC-A3 + AC-A4 — the central client: envelope parsing, typed failure translation,
 * empty-body false-success guard (R11), timeout detection, config guard before any HTTP call,
 * and token-free logging.
 */
class GhnApiClientTest extends TestCase
{
    private const OPERATION = 'master_data_provinces';

    private Curl&MockObject $httpClient;

    private Config&MockObject $config;

    /** Current token value served by the Config mock (mutated per test). */
    private string $configToken = 'test-token';

    /** @var object{records: array<int, array{level: string, message: string, context: array}>}&LoggerInterface */
    private object $psrLogger;

    private GhnApiClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->getMockBuilder(Curl::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->config = $this->createMock(Config::class);
        $this->config->method('getApiToken')->willReturnCallback(fn (): string => $this->configToken);
        $this->config->method('getShopId')->willReturn('999111');
        $this->config->method('getBaseUrl')->willReturn('https://ghn.test/api');
        $this->config->method('isDebugEnabled')->willReturn(false);
        $this->config->method('getConnectionTimeout')->willReturn(10);
        $this->config->method('getRequestTimeout')->willReturn(30);

        $this->psrLogger = new class () implements LoggerInterface {
            public array $records = [];

            public function emergency(string|\Stringable $message, array $context = []): void
            {
                $this->log('emergency', $message, $context);
            }

            public function alert(string|\Stringable $message, array $context = []): void
            {
                $this->log('alert', $message, $context);
            }

            public function critical(string|\Stringable $message, array $context = []): void
            {
                $this->log('critical', $message, $context);
            }

            public function error(string|\Stringable $message, array $context = []): void
            {
                $this->log('error', $message, $context);
            }

            public function warning(string|\Stringable $message, array $context = []): void
            {
                $this->log('warning', $message, $context);
            }

            public function notice(string|\Stringable $message, array $context = []): void
            {
                $this->log('notice', $message, $context);
            }

            public function info(string|\Stringable $message, array $context = []): void
            {
                $this->log('info', $message, $context);
            }

            public function debug(string|\Stringable $message, array $context = []): void
            {
                $this->log('debug', $message, $context);
            }

            public function log(mixed $level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };

        $this->client = new GhnApiClient(
            $this->httpClient,
            $this->config,
            new GhnErrorTranslator(),
            new GhnLogger($this->psrLogger),
            new Json()
        );
    }

    public function testSuccessEnvelopeReturnsData(): void
    {
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')
            ->willReturn((string) json_encode(['code' => 200, 'message' => 'Success', 'data' => ['items' => [1, 2]]]));

        $result = $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);

        $this->assertSame(['items' => [1, 2]], $result);
    }

    public function testGetBuildsQueryStringAndReturnsData(): void
    {
        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('https://ghn.test/api/v3/master-data/ward/all-by-province-id?province_id=1&offset=0&limit=200');
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')
            ->willReturn((string) json_encode(['code' => 200, 'message' => 'Success', 'data' => [['_id' => 11]]]));

        $result = $this->client->get(
            'new_master_data_ward',
            'v3/master-data/ward/all-by-province-id',
            ['province_id' => '1', 'offset' => 0, 'limit' => 200]
        );

        $this->assertSame([['_id' => 11]], $result);
    }

    public function testGetWithoutParamsHasNoQueryMark(): void
    {
        $this->httpClient->expects($this->once())->method('get')->with('https://ghn.test/api/master-data/province');
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')
            ->willReturn((string) json_encode(['code' => 200, 'message' => 'Success', 'data' => []]));

        $this->assertSame([], $this->client->get('legacy_master_data_province', GhnEndpoints::MASTER_DATA_PROVINCES));
    }

    public function testGetEmptyBodyStillThrowsRemote(): void
    {
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn('');

        $this->expectException(ProviderRemoteException::class);

        $this->client->get('legacy_master_data_province', GhnEndpoints::MASTER_DATA_PROVINCES);
    }

    public function testGetRetriesIdempotentReadOnTransientTimeoutThenSucceeds(): void
    {
        $getCalls = 0;
        $this->httpClient->expects($this->exactly(2))->method('get')->willReturnCallback(function () use (&$getCalls): void {
            $getCalls++;
            if ($getCalls === 1) {
                throw new \Exception('Resolving timed out after 10000 milliseconds');
            }
        });
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')
            ->willReturn((string) json_encode(['code' => 200, 'message' => 'Success', 'data' => ['ok' => 1]]));

        $result = $this->client->get('legacy_master_data_province', GhnEndpoints::MASTER_DATA_PROVINCES);

        $this->assertSame(['ok' => 1], $result);
    }

    public function testGetThrowsAfterExhaustedRetries(): void
    {
        $this->httpClient->expects($this->exactly(3))->method('get')->willReturnCallback(function (): void {
            throw new \Exception('Connection timeout after 10001 ms');
        });

        $this->expectException(ProviderTimeoutException::class);

        $this->client->get('legacy_master_data_ward', GhnEndpoints::MASTER_DATA_WARDS, ['district_id' => '1442']);
    }

    public function testPostIsNeverRetriedOnTimeout(): void
    {
        $attempts = 0;
        $this->httpClient->expects($this->exactly(1))->method('post')->willReturnCallback(function () use (&$attempts): void {
            $attempts++;
            throw new \Exception('Operation timed out after 30000 milliseconds');
        });

        try {
            $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
            $this->fail('Expected ProviderTimeoutException');
        } catch (ProviderTimeoutException) {
            // expected — POST is money-adjacent, zero auto-retry (SPEC §19)
        }
        $this->assertSame(1, $attempts);
    }

    public function testScalarEnvelopeDataIsNormalizedToEmptyArray(): void
    {
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')
            ->willReturn((string) json_encode(['code' => 200, 'message' => 'Success', 'data' => null]));

        $this->assertSame([], $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []));
    }

    public function testEnvelopeErrorThrowsTranslatedException(): void
    {
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')
            ->willReturn((string) json_encode(['code' => 401, 'message' => 'Token not found', 'data' => null]));

        $this->expectException(ProviderAuthenticationException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testEnvelopeInvalidCodeThrowsInvalidRequest(): void
    {
        // Address-free 400 message → InvalidRequest bucket (an address-shaped message like
        // "district_id not found" would classify as ProviderInvalidAddressException instead).
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')
            ->willReturn((string) json_encode(['code' => 400, 'message' => 'invalid parameters', 'data' => null]));

        $this->expectException(ProviderInvalidRequestException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_WARDS, ['district_id' => null]);
    }

    public function testEmptyBodyOnHttp200ThrowsRemote(): void
    {
        // SPIKE-9Z231Q R11 — shop-not-found quirk: HTTP 200 + empty body must never read as success.
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn('   ');

        $this->expectException(ProviderRemoteException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testMalformedJsonThrowsRemote(): void
    {
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn('not-json{{');

        $this->expectException(ProviderRemoteException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testNonEnvelopeArrayThrowsRemote(): void
    {
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn((string) json_encode(['foo' => 'bar']));

        $this->expectException(ProviderRemoteException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testHttp401ThrowsAuthentication(): void
    {
        $this->httpClient->method('getStatus')->willReturn(401);
        $this->httpClient->method('getBody')->willReturn('unauthorized');

        $this->expectException(ProviderAuthenticationException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testHttp5xxThrowsServiceUnavailable(): void
    {
        $this->httpClient->method('getStatus')->willReturn(503);
        $this->httpClient->method('getBody')->willReturn('upstream');

        $this->expectException(ProviderServiceUnavailableException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testTransportTimeoutThrowsTimeout(): void
    {
        $this->httpClient->method('post')
            ->willThrowException(new \Exception('Operation timed out after 30000 milliseconds with 0 bytes received'));

        $this->expectException(ProviderTimeoutException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testZeroHttpStatusThrowsTimeout(): void
    {
        $this->httpClient->method('getStatus')->willReturn(0);

        $this->expectException(ProviderTimeoutException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testTransportErrorThrowsRemote(): void
    {
        $this->httpClient->method('post')->willThrowException(new \Exception('Could not resolve host'));

        $this->expectException(ProviderRemoteException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testMissingConfigurationAbortsBeforeAnyHttpCall(): void
    {
        $this->configToken = '';
        $this->httpClient->expects($this->never())->method('post');

        $this->expectException(ProviderAuthenticationException::class);

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testAuthHeadersAndTimeoutsAreApplied(): void
    {
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')
            ->willReturn((string) json_encode(['code' => 200, 'message' => 'Success', 'data' => []]));

        $this->httpClient->expects($this->once())->method('setTimeout')->with(30);
        $this->httpClient->expects($this->once())
            ->method('setHeaders')
            ->with($this->callback(static function (array $headers): bool {
                return ($headers['Token'] ?? '') === 'test-token'
                    && ($headers['ShopId'] ?? '') === '999111'
                    && ($headers['Content-Type'] ?? '') === 'application/json';
            }));

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);
    }

    public function testSuccessfulCallWritesSingleAuditLineWithoutToken(): void
    {
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')
            ->willReturn((string) json_encode(['code' => 200, 'message' => 'Success', 'data' => []]));

        $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, []);

        $this->assertCount(1, $this->psrLogger->records);
        $record = $this->psrLogger->records[0];
        $this->assertSame('info', $record['level']);
        $this->assertSame(
            ['operation', 'shop_id', 'http_status', 'provider_code', 'duration_ms'],
            array_keys($record['context'])
        );
        $this->assertSame('999111', $record['context']['shop_id']);
        $this->assertSame(200, $record['context']['provider_code']);
    }

    public function testTokenNeverAppearsInAnyLogRecordOnFailurePath(): void
    {
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')
            ->willReturn((string) json_encode(['code' => 400, 'message' => 'bad request', 'data' => null]));

        try {
            $this->client->post(self::OPERATION, GhnEndpoints::MASTER_DATA_PROVINCES, ['Token' => 'test-token']);
            $this->fail('Expected ProviderInvalidRequestException');
        } catch (ProviderInvalidRequestException $exception) {
            $this->assertStringNotContainsString('test-token', $exception->getMessage());
        }

        $this->assertNotEmpty($this->psrLogger->records);
        $this->assertStringNotContainsString(
            'test-token',
            json_encode($this->psrLogger->records, JSON_THROW_ON_ERROR)
        );
    }
}
