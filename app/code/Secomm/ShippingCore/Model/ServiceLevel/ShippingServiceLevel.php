<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\ServiceLevel;

use Secomm\ShippingCore\Api\ShippingServiceLevelInterface;

/**
 * TASK-XXBN5X r1 — immutable, self-guarding service-level definition VO.
 *
 * Created by project/composition modules (DI registration into ShippingServiceLevelRegistry) —
 * never a hardcoded ShippingCore list; @see ShippingServiceLevelInterface.
 */
final class ShippingServiceLevel implements ShippingServiceLevelInterface
{
    /**
     * @param string $code stable machine identity (non-empty)
     * @param string $label configurable presentation label (non-empty)
     * @param bool $enabled whether the level is currently offered
     * @param int $sortOrder display/orchestration ordering hint (lower = earlier)
     * @throws \LogicException on an empty code or label
     */
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly bool $enabled = true,
        private readonly int $sortOrder = 0
    ) {
        if (trim($code) === '') {
            throw new \LogicException('Shipping service level requires a non-empty machine code.');
        }
        if (trim($label) === '') {
            throw new \LogicException('Shipping service level requires a non-empty label.');
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
