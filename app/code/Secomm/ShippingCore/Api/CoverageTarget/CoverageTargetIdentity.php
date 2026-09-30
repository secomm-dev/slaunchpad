<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\CoverageTarget;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * TASK-WY6WP5 — immutable identity of a coverage target: (type, code). This is the logical
 * coverage key (directive §21) — P1 identities are CARRIER targets (secomm_ghn), future
 * METHOD targets (flatrate, freeshipping, ...) reuse the same shape without changing zone
 * or scope semantics.
 *
 * The code pattern is intentionally the carrier-code alphabet (Magento carrier codes are
 * lowercase alphanumerics + underscore); METHOD codes such as `flatrate` or
 * `mageplaza_tablerate` fit the same pattern.
 */
final class CoverageTargetIdentity
{
    private const CODE_PATTERN = '/^[a-z0-9_]+$/';

    private function __construct(
        private readonly string $type,
        private readonly string $code
    ) {
    }

    /**
     * P1 factory — a carrier coverage target.
     */
    public static function carrier(string $code): self
    {
        return self::create(CoverageTargetType::CARRIER, $code);
    }

    /**
     * General factory (registry normalization, future METHOD support).
     *
     * @throws LocalizedException unknown type or malformed code
     */
    public static function create(string $type, string $code): self
    {
        if (!CoverageTargetType::exists($type)) {
            throw new LocalizedException(new Phrase(
                'Unknown coverage target type "%1". Known types: %2.',
                [$type, implode(', ', CoverageTargetType::all())]
            ));
        }
        $code = trim($code);
        if ($code === '' || preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new LocalizedException(new Phrase(
                'Invalid coverage target code "%1" (lowercase letters, digits, underscore).',
                [$code]
            ));
        }

        return new self($type, $code);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function code(): string
    {
        return $this->code;
    }

    /**
     * Stable composite key ("CARRIER:secomm_ghn") — grid row identity and duplicate guard.
     */
    public function key(): string
    {
        return $this->type . ':' . $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->code === $other->code;
    }
}
