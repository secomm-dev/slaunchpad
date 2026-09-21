<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Tracking;

use Magento\Framework\Phrase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Client\GhnEndpoints;
use Secomm\Ghn\Model\Tracking\GhnStatusMapper;
use Secomm\Ghn\Model\Tracking\GhnTrackingFetcher;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

/**
 * TASK-GKHXY1 (GHN-E1) — the reconciliation fetcher: Order Info status → the SAME
 * GhnStatusMapper the webhook uses (one mapping table, two sources); unknown/empty status →
 * null (nothing to sync); fetch failures bubble to the reconciliation service.
 */
class GhnTrackingFetcherTest extends TestCase
{
    private GhnApiClientInterface&MockObject $apiClient;

    private GhnTrackingFetcher $fetcher;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(GhnApiClientInterface::class);
        $this->fetcher = new GhnTrackingFetcher($this->apiClient, new GhnStatusMapper());
    }

    public function testFetchesThroughTheSharedMapper(): void
    {
        $this->apiClient->expects($this->once())->method('get')->with(
            'order_info',
            GhnEndpoints::ORDER_INFO,
            ['order_code' => 'L8TKYG']
        )->willReturn(['status' => 'delivering', 'leadtime' => 1789232399]);

        $update = $this->fetcher->fetch('L8TKYG');

        $this->assertNotNull($update);
        $this->assertSame('secomm_ghn', $update->getCarrierCode());
        $this->assertSame('L8TKYG', $update->getTrackingNumber());
        $this->assertSame(NormalizedTrackingStatus::OUT_FOR_DELIVERY, $update->getNormalizedStatus());
        $this->assertSame('delivering', $update->getCarrierStatusCode());
        $this->assertSame('api', $update->getSource());
    }

    public function testLostStatusNormalizesThroughTheSharedMapper(): void
    {
        $this->apiClient->method('get')->willReturn(['status' => 'lost']);

        $update = $this->fetcher->fetch('L8TKYG');

        $this->assertNotNull($update);
        $this->assertSame(NormalizedTrackingStatus::LOST, $update->getNormalizedStatus());
    }

    public function testDamagedStatusNormalizesThroughTheSharedMapper(): void
    {
        $this->apiClient->method('get')->willReturn(['status' => 'damage']);

        $update = $this->fetcher->fetch('L8TKYG');

        $this->assertSame(NormalizedTrackingStatus::DAMAGED, $update->getNormalizedStatus());
    }

    public function testEmptyStatusYieldsNull(): void
    {
        $this->apiClient->method('get')->willReturn(['status' => '']);

        $this->assertNull($this->fetcher->fetch('L8TKYG'));
    }

    public function testFetchFailureBubblesToTheReconciliationService(): void
    {
        $this->apiClient->method('get')->willThrowException(
            new ProviderTimeoutException(new Phrase('timed out'))
        );

        $this->expectException(ProviderTimeoutException::class);
        $this->fetcher->fetch('L8TKYG');
    }
}
