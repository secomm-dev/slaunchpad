<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Shipment;

use Magento\Backend\Model\Session;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Shipment\OfflineEligibilitySession;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — order-scoped stash hand-off between a carrier's blocked
 * online attempt and the offline control: pull reads + CLEARS, other orders are untouched.
 * The backend session writes via the SessionManager magic __call (set*) — read side is the
 * native getData($key, $clear).
 */
class OfflineEligibilitySessionTest extends TestCase
{
    private Session&MockObject $session;

    private OfflineEligibilitySession $eligibility;

    protected function setUp(): void
    {
        $this->session = $this->createMock(Session::class);
        $this->eligibility = new OfflineEligibilitySession($this->session);
    }

    private function emulateSessionStorage(): void
    {
        $storage = [];
        $this->session->method('getData')->willReturnCallback(
            function (string $key = '', bool $clear = false) use (&$storage) {
                $value = $storage[$key] ?? null;
                if ($clear) {
                    unset($storage[$key]);
                }

                return $value;
            }
        );
        $this->session->method('__call')->willReturnCallback(
            function (string $method, array $args) use (&$storage) {
                if ($method === 'setData') {
                    $storage[$args[0]] = $args[1];

                    return $storage[$args[0]];
                }

                return null;
            }
        );
    }

    public function testStashPullRoundTrip(): void
    {
        $this->emulateSessionStorage();
        $this->eligibility->stash(21, 'INVALID_PARCEL', 'package #1 is above the limit');

        self::assertSame(
            ['reason_code' => 'INVALID_PARCEL', 'message' => 'package #1 is above the limit'],
            $this->eligibility->pull(21)
        );
    }

    public function testPullClearsAndLeavesOtherOrdersUntouched(): void
    {
        $this->emulateSessionStorage();
        $this->eligibility->stash(21, 'INVALID_PARCEL', 'one');
        $this->eligibility->stash(22, 'INVALID_CONFIGURATION', 'two');

        self::assertNotNull($this->eligibility->pull(21));
        self::assertNull($this->eligibility->pull(21), 'second pull is empty (cleared)');
        self::assertSame(
            ['reason_code' => 'INVALID_CONFIGURATION', 'message' => 'two'],
            $this->eligibility->pull(22)
        );
    }

    public function testEmptySessionAndInvalidOrderAreNull(): void
    {
        $this->session->method('getData')->willReturn(null);

        self::assertNull($this->eligibility->pull(21));
        self::assertNull($this->eligibility->pull(0));
    }

    public function testStashIgnoresInvalidOrder(): void
    {
        $this->session->expects($this->never())->method('__call');

        $this->eligibility->stash(0, 'INVALID_PARCEL', 'ignored');
    }
}
