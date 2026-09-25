<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\CoverageTarget;

/**
 * TASK-WY6WP5 — coverage TARGET type. The Shipping Coverage admin is target-generic:
 * a coverage policy is addressed by (type, code), not by a carrier code alone.
 *
 * P1 implements CARRIER only. METHOD is a RESERVED constant (directive §2/§21/§23): no
 * registry producer, no admin surface, no runtime consumer and no persistence semantics
 * in P1 — registering METHOD targets or gating method execution is future work that must
 * reopen with a real consumer. The constant exists so the identity model and the registry
 * normalization can name the future axis without speculative capability matrices.
 */
final class CoverageTargetType
{
    public const CARRIER = 'CARRIER';

    /** Reserved for future method-level coverage (Flat Rate, Free Shipping, Table Rate, ...). */
    public const METHOD = 'METHOD';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::CARRIER, self::METHOD];
    }

    public static function exists(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * Whether the type has a P1 implementation (registry producers + admin surface).
     * METHOD returns false until a future task implements it.
     */
    public static function isImplemented(string $type): bool
    {
        return $type === self::CARRIER;
    }
}
