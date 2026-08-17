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
use Secomm\Ghtk\Model\Tracking\WebhookPayloadParser;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

class WebhookPayloadParserTest extends TestCase
{
    private WebhookPayloadParser $parser;

    protected function setUp(): void
    {
        $this->parser = new WebhookPayloadParser(new GhtkStatusMapper());
    }

    public function testParsesLabelIdAndStatus(): void
    {
        $update = $this->parser->parse('{"label_id": "S10001.P1", "status_id": 5, "message": "Đã giao hàng", "updated_at": 1755432000}');

        $this->assertNotNull($update);
        $this->assertSame('ghtk', $update->getCarrierCode());
        $this->assertSame('S10001.P1', $update->getTrackingNumber());
        $this->assertSame(NormalizedTrackingStatus::DELIVERED, $update->getNormalizedStatus());
        $this->assertSame('5', $update->getCarrierStatusCode());
        $this->assertSame('Đã giao hàng', $update->getCarrierStatusMessage());
        $this->assertSame(1755432000, $update->getOccurredAt());
        $this->assertSame('webhook', $update->getSource());
        $this->assertSame('S10001.P1', $update->getRaw()['label_id']);
    }

    public function testAlternativeFieldNamesAccepted(): void
    {
        $update = $this->parser->parse('{"tracking_code": "S9.T", "status": 4}');

        $this->assertNotNull($update);
        $this->assertSame('S9.T', $update->getTrackingNumber());
        $this->assertSame(NormalizedTrackingStatus::IN_TRANSIT, $update->getNormalizedStatus());
    }

    public function testUnknownStatusYieldsUnknownNotFailure(): void
    {
        $update = $this->parser->parse('{"label_id": "S10001.P1", "status_id": 9999}');

        $this->assertNotNull($update);
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $update->getNormalizedStatus());
        $this->assertSame('9999', $update->getCarrierStatusCode());
    }

    public function testInvalidJsonReturnsNull(): void
    {
        $this->assertNull($this->parser->parse('not-json{'));
    }

    public function testMissingIdentifierOrStatusReturnsNull(): void
    {
        $this->assertNull($this->parser->parse('{"message": "no ids"}'));
        $this->assertNull($this->parser->parse('{"label_id": "S1"}'));
    }

    public function testSensitiveKeysStrippedFromRaw(): void
    {
        $update = $this->parser->parse('{"label_id": "S1", "status_id": 3, "token": "abc", "secret": "xyz"}');

        $this->assertNotNull($update);
        $this->assertArrayNotHasKey('token', $update->getRaw());
        $this->assertArrayNotHasKey('secret', $update->getRaw());
    }

    public function testIdentifierCandidatesOrderedAndUnique(): void
    {
        $payload = ['label_id' => 'L1', 'tracking_code' => 'T1', 'tracking' => 'L1', 'order_code' => 'O1'];

        $this->assertSame(['L1', 'T1', 'O1'], $this->parser->identifierCandidates($payload));
    }
}
