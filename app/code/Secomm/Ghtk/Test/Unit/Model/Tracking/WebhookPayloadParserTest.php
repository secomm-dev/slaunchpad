<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Tracking;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Tracking\GhtkStatusMapper;
use Secomm\Ghtk\Model\Tracking\WebhookPayloadParser;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

/**
 * TASK-KCXKVR — the official webhook sample posts application/x-www-form-urlencoded
 * (JSON sample also documented): BOTH formats parse; `action_time` (ISO 8601) feeds
 * occurredAt; invalid/missing timestamp → null without failing the update.
 */
class WebhookPayloadParserTest extends TestCase
{
    private WebhookPayloadParser $parser;

    protected function setUp(): void
    {
        $this->parser = new WebhookPayloadParser(new GhtkStatusMapper());
    }

    public function testParsesOfficialFormUrlencodedPayload(): void
    {
        // Exact official sample (api.ghtk.vn webhook docs, form-urlencoded variant).
        $raw = 'label_id=S1.A1.17373471&partner_id=1234567&action_time=2016-11-02T12:18:39%2B07:00'
            . '&status_id=5&reason_code=&reason=&weight=2.4&fee=1500&return_part_package=0';

        $update = $this->parser->parse($raw);

        $this->assertNotNull($update);
        $this->assertSame('S1.A1.17373471', $update->getTrackingNumber());
        $this->assertSame('5', $update->getCarrierStatusCode());
        $this->assertSame(NormalizedTrackingStatus::DELIVERED, $update->getNormalizedStatus());
        $this->assertSame('webhook', $update->getSource());
        $this->assertSame(strtotime('2016-11-02T12:18:39+07:00'), $update->getOccurredAt());
    }

    public function testParsesJsonPayload(): void
    {
        $update = $this->parser->parse('{"label_id": "S10001.P1", "status_id": 5, "message": "Đã giao hàng"}');

        $this->assertNotNull($update);
        $this->assertSame(NormalizedTrackingStatus::DELIVERED, $update->getNormalizedStatus());
        $this->assertSame('Đã giao hàng', $update->getCarrierStatusMessage());
    }

    public function testActionTimeIso8601FeedsOccurredAt(): void
    {
        $update = $this->parser->parsePayload([
            'label_id' => 'L1',
            'status_id' => 12,
            'action_time' => '2026-09-14T08:30:00+07:00',
        ]);

        $this->assertNotNull($update);
        $this->assertSame(strtotime('2026-09-14T08:30:00+07:00'), $update->getOccurredAt());
        // Official 12 = "Đã điều phối lấy hàng/Đang lấy hàng" — PICKING, never RETURNING.
        $this->assertSame(NormalizedTrackingStatus::PICKING, $update->getNormalizedStatus());
    }

    public function testInvalidActionTimeYieldsNullOccurredAtButStillParses(): void
    {
        $update = $this->parser->parsePayload([
            'label_id' => 'L1',
            'status_id' => 21,
            'action_time' => 'not-a-timestamp',
        ]);

        $this->assertNotNull($update);
        $this->assertNull($update->getOccurredAt());
        // Official 21 = "Đã trả hàng" — must surface as RETURNED (previously UNKNOWN).
        $this->assertSame(NormalizedTrackingStatus::RETURNED, $update->getNormalizedStatus());
    }

    public function testMissingActionTimeYieldsNullOccurredAt(): void
    {
        $update = $this->parser->parse('label_id=L2&status_id=3');

        $this->assertNotNull($update);
        $this->assertNull($update->getOccurredAt());
    }

    public function testAlternativeFieldNamesAccepted(): void
    {
        $update = $this->parser->parse('{"tracking_code": "S9.T", "status": 4}');

        $this->assertNotNull($update);
        $this->assertSame(NormalizedTrackingStatus::OUT_FOR_DELIVERY, $update->getNormalizedStatus());
    }

    public function testUnknownStatusYieldsUnknownNotFailure(): void
    {
        $update = $this->parser->parse('{"label_id": "S10001.P1", "status_id": 9999}');

        $this->assertNotNull($update);
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $update->getNormalizedStatus());
        $this->assertSame('9999', $update->getCarrierStatusCode());
    }

    public function testInformationalShipperStatusStaysUnknown(): void
    {
        // Official docs: shipper reports are "không phải trạng thái của đơn hàng".
        $update = $this->parser->parse('{"label_id": "L1", "status_id": 123}');

        $this->assertNotNull($update);
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $update->getNormalizedStatus());
    }

    public function testEmptyBodyReturnsNull(): void
    {
        $this->assertNull($this->parser->decode(''));
        $this->assertNull($this->parser->parse(''));
    }

    public function testGarbageBodyReturnsNull(): void
    {
        $this->assertNull($this->parser->parse('not-json{'));
    }

    public function testMissingIdentifierOrStatusReturnsNull(): void
    {
        $this->assertNull($this->parser->parse('{"message": "no ids"}'));
        $this->assertNull($this->parser->parse('{"label_id": "S1"}'));
        $this->assertNull($this->parser->parse('reason=nothing'));
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

    public function testPartnerIdIsAnIdentifierCandidate(): void
    {
        // Official payload carries partner_id too — reconciliation fallback.
        $payload = ['label_id' => 'L1', 'partner_id' => 'P1'];

        $this->assertSame(['L1', 'P1'], $this->parser->identifierCandidates($payload));
    }

    public function testDuplicateWebhookParsesToTheSameUpdate(): void
    {
        // GHTK may retry once on non-200 — the parser must be deterministic so a
        // replayed update feeds the (idempotent) shared processor unchanged.
        $raw = 'label_id=S1&partner_id=P1&status_id=5&action_time=2026-09-14T08:30:00%2B07:00';

        $first = $this->parser->parse($raw);
        $second = $this->parser->parse($raw);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->getTrackingNumber(), $second->getTrackingNumber());
        $this->assertSame($first->getNormalizedStatus(), $second->getNormalizedStatus());
        $this->assertSame($first->getOccurredAt(), $second->getOccurredAt());
    }
}
