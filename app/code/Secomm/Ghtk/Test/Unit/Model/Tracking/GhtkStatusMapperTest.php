<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Tracking;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Tracking\GhtkStatusMapper;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

class GhtkStatusMapperTest extends TestCase
{
    private GhtkStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GhtkStatusMapper();
    }

    /**
     * @dataProvider ghtkCodes
     */
    public function testKnownCodes(mixed $code, string $expected): void
    {
        $this->assertSame($expected, $this->mapper->map($code));
    }

    public function ghtkCodes(): array
    {
        return [
            'cancel -1' => [-1, NormalizedTrackingStatus::CANCELLED],
            'pending 1' => ['1', NormalizedTrackingStatus::CREATED],
            'accepted 2' => ['2', NormalizedTrackingStatus::PICKING],
            'picked 3' => ['3', NormalizedTrackingStatus::PICKED_UP],
            'dispatched 4' => ['4', NormalizedTrackingStatus::IN_TRANSIT],
            'delivered 5' => ['5', NormalizedTrackingStatus::DELIVERED],
            'fail 6' => ['6', NormalizedTrackingStatus::DELIVERY_FAILED],
            'delivering 10' => ['10', NormalizedTrackingStatus::OUT_FOR_DELIVERY],
            'not delivered 11' => ['11', NormalizedTrackingStatus::DELIVERY_FAILED],
            'returning 12' => ['12', NormalizedTrackingStatus::RETURNING],
            'returned 13' => ['13', NormalizedTrackingStatus::RETURNED],
            'returning to shop 20' => ['20', NormalizedTrackingStatus::RETURNING],
        ];
    }

    public function testUnknownCodeMapsToUnknown(): void
    {
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $this->mapper->map('9999'));
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $this->mapper->map(''));
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $this->mapper->map('Đây là trạng thái mới'));
    }
}
