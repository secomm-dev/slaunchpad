<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Tracking;

use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Tracking\GhnStatusMapper;
use Secomm\Ghn\Model\Tracking\WebhookPayloadParser;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

/**
 * TASK-GKHXY1 (GHN-E1) — webhook payload parsing: only `Type=switch_status` with an OrderCode +
 * Status becomes a TrackingUpdate (webhook source, occurredAt from Time, sanitized raw — no
 * shipper PII / shop identifiers). Every other documented event type is acknowledged-and-dropped
 * (null) — never fed into the tracking pipeline as a fake status.
 */
class WebhookPayloadParserTest extends TestCase
{
    private WebhookPayloadParser $parser;

    protected function setUp(): void
    {
        $this->parser = new WebhookPayloadParser(new GhnStatusMapper());
    }

    public function testParsesSwitchStatusPayload(): void
    {
        $update = $this->parser->parse([
            'ShopID' => 200537,
            'Time' => '2026-09-15 10:00:00',
            'OrderCode' => 'L8TKYG',
            'ClientOrderCode' => 'GHNS10',
            'Type' => 'switch_status',
            'Description' => 'Đang giao hàng',
            'Status' => 'delivering',
            'Reason' => '',
            'ReasonCode' => '',
            'CODAmount' => 0,
            'ShipperName' => 'Tài Xế',
            'ShipperPhone' => '0987654321',
            'PodURL' => 'https://example.com/pod.pdf',
        ]);

        $this->assertNotNull($update);
        $this->assertSame('secomm_ghn', $update->getCarrierCode());
        $this->assertSame('L8TKYG', $update->getTrackingNumber());
        $this->assertSame(NormalizedTrackingStatus::OUT_FOR_DELIVERY, $update->getNormalizedStatus());
        $this->assertSame('delivering', $update->getCarrierStatusCode());
        $this->assertSame('webhook', $update->getSource());
        $this->assertSame(strtotime('2026-09-15 10:00:00'), $update->getOccurredAt());
    }

    public function testRawIsSanitizedToSafeDiagnosticKeysOnly(): void
    {
        $update = $this->parser->parse([
            'OrderCode' => 'L8TKYG',
            'ClientOrderCode' => 'GHNS10',
            'Type' => 'switch_status',
            'Status' => 'delivering',
            'Time' => '2026-09-15 10:00:00',
            'ShipperName' => 'Tài Xế',
            'ShipperPhone' => '0987654321',
            'PodURL' => 'https://example.com/pod.pdf',
            'ShopID' => 200537,
        ]);

        $raw = $update->getRaw();
        foreach (['ShipperName', 'ShipperPhone', 'PodURL', 'ShopID'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $raw, "$forbidden is PII/credential-adjacent — never persisted");
        }
        $this->assertSame(['OrderCode', 'ClientOrderCode', 'Status', 'Type', 'Time'], array_keys($raw));
    }

    public function testNonSwitchStatusTypesAreDropped(): void
    {
        foreach (['create', 'update_weight', 'update_cod', 'update_fee', 'update_payment_type', 'cod', 'update_partial_return'] as $type) {
            $this->assertNull(
                $this->parser->parse(['Type' => $type, 'OrderCode' => 'L8TKYG', 'Status' => 'delivering']),
                "$type is not a lifecycle transition"
            );
        }
    }

    public function testMissingOrderCodeOrStatusIsDropped(): void
    {
        $this->assertNull($this->parser->parse(['Type' => 'switch_status', 'Status' => 'delivering']));
        $this->assertNull($this->parser->parse(['Type' => 'switch_status', 'OrderCode' => 'L8TKYG']));
    }

    public function testUnknownStatusStillMapsThroughWithTheRawCodePreserved(): void
    {
        $update = $this->parser->parse([
            'OrderCode' => 'L8TKYG',
            'Type' => 'switch_status',
            'Status' => 'martian_status',
            'Time' => '2026-09-15 10:00:00',
        ]);

        $this->assertNotNull($update);
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $update->getNormalizedStatus());
        $this->assertSame('martian_status', $update->getCarrierStatusCode());
    }

    public function testUnparseableTimeYieldsNullOccurredAt(): void
    {
        $update = $this->parser->parse([
            'OrderCode' => 'L8TKYG',
            'Type' => 'switch_status',
            'Status' => 'delivered',
            'Time' => 'not-a-date',
        ]);

        $this->assertNull($update->getOccurredAt(), 'an unparseable carrier time must not poison the guard');
    }

    public function testReasonFlowsIntoTheCarrierStatusMessage(): void
    {
        $update = $this->parser->parse([
            'OrderCode' => 'L8TKYG',
            'Type' => 'switch_status',
            'Status' => 'delivery_fail',
            'Reason' => 'Khách hàng vắng nhà',
            'ReasonCode' => 'CUSTOMER_ABSENT',
            'Time' => '2026-09-15 10:00:00',
        ]);

        $this->assertSame('Khách hàng vắng nhà', $update->getCarrierStatusMessage());
        $this->assertSame(NormalizedTrackingStatus::DELIVERY_FAILED, $update->getNormalizedStatus());
    }
}
