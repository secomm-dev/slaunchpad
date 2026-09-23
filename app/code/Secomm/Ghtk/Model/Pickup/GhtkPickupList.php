<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Pickup;

/**
 * TASK-3HPB76 — parsed `list_pick_add` payload (official contract: `{success,
 * message, data[]}` with `pick_address_id`, `address`, `pick_tel`, `pick_name`
 * per row — the merchant's registered pickup/warehouse list, NOT administrative
 * master data).
 *
 * Row policy: a row without a usable `pick_address_id` is SKIPPED (counted in
 * `skippedRows`) — validation is exact-ID based (§7/§12), never text/fuzzy.
 */
final class GhtkPickupList
{
    /** @param GhtkPickupAddress[] $addresses */
    private function __construct(
        private readonly array $addresses,
        private readonly int $skippedRows
    ) {
    }

    /**
     * Defensive parse: works for both `{success, data[]}` envelopes and a bare
     * `data[]` array; unusable top-level shapes yield an empty list (the service
     * decides the technical/unusable classification from the raw response).
     *
     * @param array $response
     */
    public static function fromResponse(array $response): self
    {
        $rows = $response['data'] ?? null;
        if (!is_array($rows)) {
            return new self([], 0);
        }

        $addresses = [];
        $skipped = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $id = $row['pick_address_id'] ?? null;
            $id = is_int($id) ? (string) $id : (is_string($id) ? trim($id) : '');
            if ($id === '') {
                $skipped++;
                continue;
            }
            $addresses[] = new GhtkPickupAddress(
                $id,
                self::stringOrNull($row['pick_name'] ?? null),
                self::stringOrNull($row['pick_tel'] ?? null),
                self::stringOrNull($row['address'] ?? null)
            );
        }

        return new self($addresses, $skipped);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    /** @return GhtkPickupAddress[] */
    public function getAddresses(): array
    {
        return $this->addresses;
    }

    public function count(): int
    {
        return count($this->addresses);
    }

    public function getSkippedRows(): int
    {
        return $this->skippedRows;
    }

    /** Exact pick_address_id lookup (string compare — the ID is the validation key). */
    public function hasId(string $id): bool
    {
        foreach ($this->addresses as $address) {
            if ($address->id === $id) {
                return true;
            }
        }

        return false;
    }

    public function getById(string $id): ?GhtkPickupAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->id === $id) {
                return $address;
            }
        }

        return null;
    }
}
