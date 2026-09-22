<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Model\ZaloPayConfigProvider;

/**
 * UNIT - checkout logo resolution (TASK-MCHN2T feature C, AC8/AC9):
 * an uploaded logo is served from MEDIA storage (survives static content
 * deploy); the website-scope upload wins over the default scope; with no
 * upload configured the bundled module asset is used (unchanged fallback).
 * The renderer keeps consuming the single `logoSrc` key.
 */
class ZaloPayConfigProviderTest extends TestCase
{
    private const MEDIA_BASE = 'https://store.example.com/media/';

    private Repository|MockObject $assetRepository;

    private ScopeConfigInterface|MockObject $scopeConfig;

    private StoreManagerInterface|MockObject $storeManager;

    private UrlInterface|MockObject $urlBuilder;

    private ZaloPayConfigProvider $provider;

    protected function setUp(): void
    {
        $this->assetRepository = $this->createMock(Repository::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $store = $this->createMock(Store::class);
        $store->method('getWebsiteId')->willReturn(1);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_MEDIA)->willReturn(self::MEDIA_BASE);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->provider = new ZaloPayConfigProvider(
            $this->assetRepository,
            $this->scopeConfig,
            $this->storeManager,
            $this->urlBuilder
        );
    }

    /**
     * A website-scope upload is served from media storage with the scope
     * prefix embedded in the stored value.
     */
    public function testWebsiteUploadIsServedFromMediaStorage(): void
    {
        $this->scopeConfig->expects($this->once())->method('getValue')->with(
            'payment/zalopay/logo',
            ScopeInterface::SCOPE_WEBSITE,
            1
        )->willReturn('websites/1/custom.png');

        $config = $this->provider->getConfig();

        $this->assertSame(
            self::MEDIA_BASE . 'zalopay/websites/1/custom.png',
            $config['payment']['zalopay']['logoSrc']
        );
    }

    /**
     * With no website upload the default-scope value applies (stored
     * without scope prefix).
     */
    public function testDefaultUploadAppliesWhenWebsiteEmpty(): void
    {
        $this->scopeConfig->expects($this->exactly(2))->method('getValue')->willReturnCallback(
            function (string $path, ?string $scopeType = null, $scopeCode = null): ?string {
                if ($scopeType === ScopeInterface::SCOPE_WEBSITE) {
                    return '';
                }

                return 'default-logo.webp';
            }
        );

        $config = $this->provider->getConfig();

        $this->assertSame(self::MEDIA_BASE . 'zalopay/default-logo.webp', $config['payment']['zalopay']['logoSrc']);
    }

    /**
     * AC8/AC9 fallback: no upload configured -> the bundled module asset,
     * exactly the pre-TASK-MCHN2T behavior.
     */
    public function testFallsBackToBundledAssetWhenNoUpload(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');
        $this->assetRepository->expects($this->once())->method('getUrl')
            ->with('Secomm_ZaloPay::images/logo.png')->willReturn('https://store.example.com/static/zalopay.png');

        $config = $this->provider->getConfig();

        $this->assertSame('https://store.example.com/static/zalopay.png', $config['payment']['zalopay']['logoSrc']);
    }

    /**
     * The provider keeps the single `logoSrc` contract and the payment-first
     * redirectUrl.
     */
    public function testKeepsSingleLogoSrcContractAndRedirectUrl(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');
        $this->assetRepository->method('getUrl')->willReturn('asset-url');
        $this->urlBuilder->method('getUrl')->with('zalopay/payment/start')->willReturn('start-url');

        $config = $this->provider->getConfig();

        $this->assertSame(
            ['redirectUrl' => 'start-url', 'logoSrc' => 'asset-url'],
            $config['payment']['zalopay']
        );
    }
}
