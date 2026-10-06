<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Client;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;
use Secomm\EInvoiceMisa\Model\Client\MisaApiQueryBuilder;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;

/**
 * Unit tests for MeInvoice HTTP client.
 */
class MisaApiClientTest extends TestCase
{
    private MisaApiClient $client;
    private Curl&MockObject $curl;
    private Json&MockObject $json;
    private MisaConfig&MockObject $config;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->curl = $this->createMock(Curl::class);
        $this->json = $this->createMock(Json::class);
        $this->config = $this->createMock(MisaConfig::class);
        $logger = $this->createMock(LoggerInterface::class);
        $queryBuilder = new MisaApiQueryBuilder($this->config);
        $this->config->method('isInvoiceWithCode')->willReturn(true);
        $this->config->method('isInvoiceCalculatingMachine')->willReturn(false);

        $this->client = new MisaApiClient($this->curl, $this->json, $this->config, $queryBuilder, $logger);
    }

    /**
     * @return void
     */
    public function testGetIntegrationTokenReturnsDataToken(): void
    {
        $this->config->method('getAuthTokenUrl')->willReturn('https://testapi.meinvoice.vn/api/integration/auth/token');
        $this->config->method('getAppId')->willReturn('app-id');
        $this->config->method('getTaxCode')->willReturn('2222222222-361');
        $this->config->method('getUsername')->willReturn('user@test');
        $this->config->method('getPassword')->willReturn('secret');

        $this->json->method('serialize')->willReturn('{}');
        $this->curl->expects(self::once())->method('post');
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"success":true,"data":"token-abc"}');
        $this->json->method('unserialize')->willReturn(['success' => true, 'data' => 'token-abc']);

        self::assertSame('token-abc', $this->client->getIntegrationToken(1));
    }

    /**
     * @return void
     */
    public function testPublishInvoiceHsmIncludesSignTypeAndInvoiceData(): void
    {
        $this->config->method('getInvoiceApiBaseUrl')->willReturn('https://testapi.meinvoice.vn/api/integration/invoice');
        $this->config->method('getSignType')->willReturn(2);
        $this->config->method('getTaxCode')->willReturn('2222222222-361');
        $this->json->method('serialize')->willReturn('{}');
        $this->json->method('unserialize')->willReturn(['success' => true, 'publishInvoiceResult' => '[]']);
        $this->curl->expects(self::once())->method('post');
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"success":true,"publishInvoiceResult":"[]"}');

        $response = $this->client->publishInvoiceHsm(['RefID' => 'r1'], 'bearer-token', 1);

        self::assertTrue((bool) $response['success']);
        self::assertArrayHasKey('publishInvoiceResult', $response);
    }
}

