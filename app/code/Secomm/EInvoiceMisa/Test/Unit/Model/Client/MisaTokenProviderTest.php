<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Client;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;
use Secomm\EInvoiceMisa\Model\Client\MisaTokenProvider;

/**
 * Unit tests for cached MeInvoice token provider.
 */
class MisaTokenProviderTest extends TestCase
{
    /**
     * @return void
     */
    public function testGetTokenReturnsCachedValueWhenAvailable(): void
    {
        $apiClient = $this->createMock(MisaApiClient::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn('cached-token');
        $apiClient->expects(self::never())->method('getIntegrationToken');

        $provider = new MisaTokenProvider($apiClient, $cache);

        self::assertSame('cached-token', $provider->getToken(1));
    }

    /**
     * @return void
     */
    public function testGetTokenFetchesAndCachesWhenCacheMiss(): void
    {
        $apiClient = $this->createMock(MisaApiClient::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn('');
        $apiClient->method('getIntegrationToken')->willReturn('new-token');
        $cache->expects(self::once())->method('save');

        $provider = new MisaTokenProvider($apiClient, $cache);

        self::assertSame('new-token', $provider->getToken(1));
    }
}

