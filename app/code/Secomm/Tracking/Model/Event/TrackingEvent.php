<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Event;

/**
 * FEAT-31X6N2 / SPEC-FEAT-31X6N2 §4 — the Launchpad tracking event contract.
 *
 * Canonical names (GA4 commerce). Adapters map to vendor names; the normalizer
 * never knows about vendors. event_id is deterministic (spec §7) and is the
 * browser/server dedup key for Meta and TikTok.
 *
 * @see EventNormalizer
 */
final class TrackingEvent
{
    public const EVENT_VIEW_ITEM = 'view_item';
    public const EVENT_VIEW_CATEGORY = 'view_category';
    public const EVENT_SEARCH = 'search';
    public const EVENT_ADD_TO_CART = 'add_to_cart';
    public const EVENT_BEGIN_CHECKOUT = 'begin_checkout';
    public const EVENT_PURCHASE = 'purchase';
    public const EVENT_REFUND = 'refund';

    public const SOURCE_BROWSER = 'browser';
    public const SOURCE_SERVER = 'server';

    /**
     * @param string $event One of the EVENT_* constants
     * @param string $eventId Deterministic id, e.g. purchase-{increment_id}
     * @param int $eventTime Unix seconds UTC
     * @param string $currency Display currency code (ISO-4217)
     * @param float $value Total value in display currency
     * @param string|null $orderId Order increment id when applicable
     * @param Item[] $items Line items
     * @param UserData|null $user Hashed user data + matching params
     * @param array{analytics: bool, marketing: bool} $consent Consent state at capture time
     * @param string $source self::SOURCE_*
     */
    public function __construct(
        public readonly string $event,
        public readonly string $eventId,
        public readonly int $eventTime,
        public readonly string $currency,
        public readonly float $value,
        public readonly ?string $orderId,
        public readonly array $items,
        public readonly ?UserData $user,
        public readonly array $consent,
        public readonly string $source
    ) {
    }

    /**
     * JSON representation persisted to the outbox payload column — identifiers
     * are already hashed inside UserData when this is serialized (AC-4).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'event_id' => $this->eventId,
            'event_time' => $this->eventTime,
            'currency' => $this->currency,
            'value' => $this->value,
            'order_id' => $this->orderId,
            'items' => array_map(static fn (Item $item): array => $item->toArray(), $this->items),
            'user' => $this->user?->toArray(),
            'consent' => $this->consent,
            'source' => $this->source,
        ];
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        return (string)json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Clone with the consent state resolved at dispatch time (observers stamp
     * this before enqueue — spec §8).
     */
    public function withConsent(bool $analytics, bool $marketing): self
    {
        return new self(
            $this->event,
            $this->eventId,
            $this->eventTime,
            $this->currency,
            $this->value,
            $this->orderId,
            $this->items,
            $this->user,
            ['analytics' => $analytics, 'marketing' => $marketing],
            $this->source
        );
    }

    /**
     * Clone with a replaced event_time — used by the backfill command so legacy
     * orders (older than the platforms' 7-day window) can still exercise the
     * pipeline. Timestamp semantics note: Meta/TikTok reject events older than
     * 7 days outright, so a backfill stamps "now"; attribution value of such
     * events is approximate by definition.
     */
    public function withEventTime(int $eventTime): self
    {
        return new self(
            $this->event,
            $this->eventId,
            $eventTime,
            $this->currency,
            $this->value,
            $this->orderId,
            $this->items,
            $this->user,
            $this->consent,
            $this->source
        );
    }

    /**
     * Rehydrate from the outbox payload (flush-side). Field trust boundary: the
     * payload was written by EnqueueService from a server-built event — user data
     * comes back as-is (already hashed at enqueue time).
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $user = null;
        if (is_array($data['user'] ?? null)) {
            $u = $data['user'];
            $user = new UserData(
                emailSha256: $u['email_sha256'] ?? null,
                phoneSha256: $u['phone_sha256'] ?? null,
                externalIdSha256: $u['external_id_sha256'] ?? null,
                fbp: $u['fbp'] ?? null,
                fbc: $u['fbc'] ?? null,
                ttclid: $u['ttclid'] ?? null,
                ttp: $u['ttp'] ?? null,
                clientIp: $u['client_ip'] ?? null,
                clientUserAgent: $u['client_user_agent'] ?? null
            );
        }

        $items = [];
        foreach (($data['items'] ?? []) as $item) {
            $items[] = new Item(
                itemId: (string)($item['item_id'] ?? ''),
                itemName: (string)($item['item_name'] ?? ''),
                itemCategory: $item['item_category'] ?? null,
                price: (float)($item['price'] ?? 0),
                quantity: (float)($item['quantity'] ?? 0)
            );
        }

        return new self(
            event: (string)($data['event'] ?? ''),
            eventId: (string)($data['event_id'] ?? ''),
            eventTime: (int)($data['event_time'] ?? 0),
            currency: (string)($data['currency'] ?? ''),
            value: (float)($data['value'] ?? 0),
            orderId: $data['order_id'] ?? null,
            items: $items,
            user: $user,
            consent: [
                'analytics' => (bool)($data['consent']['analytics'] ?? false),
                'marketing' => (bool)($data['consent']['marketing'] ?? false),
            ],
            source: (string)($data['source'] ?? self::SOURCE_SERVER)
        );
    }
}
