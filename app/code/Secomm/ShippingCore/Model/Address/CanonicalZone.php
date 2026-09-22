<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;

/**
 * TASK-8MQHJX (Phase A) — immutable canonical destination zone VO;
 * @see CanonicalZoneInterface.
 */
final class CanonicalZone implements CanonicalZoneInterface
{
    /**
     * @param string $code stable machine identity (non-empty)
     * @param string $label human-readable label (non-empty)
     * @param bool $enabled
     * @param string[] $includeProvinceCodes canonical province codes
     * @param string[] $includeWardCodes canonical ward codes (empty = no positive ward restriction)
     * @param string[] $excludeWardCodes canonical ward codes always excluded
     * @throws \InvalidArgumentException on empty code/label or non-array code lists
     */
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly bool $enabled = true,
        private readonly array $includeProvinceCodes = [],
        private readonly array $includeWardCodes = [],
        private readonly array $excludeWardCodes = []
    ) {
        if (trim($code) === '') {
            throw new \InvalidArgumentException('Canonical zone requires a non-empty code.');
        }
        if (trim($label) === '') {
            throw new \InvalidArgumentException('Canonical zone requires a non-empty label.');
        }
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getIncludeProvinceCodes(): array
    {
        return $this->includeProvinceCodes;
    }

    public function getIncludeWardCodes(): array
    {
        return $this->includeWardCodes;
    }

    public function getExcludeWardCodes(): array
    {
        return $this->excludeWardCodes;
    }
}
