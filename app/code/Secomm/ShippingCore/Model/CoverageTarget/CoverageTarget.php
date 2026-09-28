<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\CoverageTarget;

use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetInterface;

/**
 * TASK-WY6WP5 — immutable registered-target metadata (identity + admin label).
 */
final class CoverageTarget implements CoverageTargetInterface
{
    public function __construct(
        private readonly CoverageTargetIdentity $identity,
        private readonly string $label
    ) {
    }

    public function getIdentity(): CoverageTargetIdentity
    {
        return $this->identity;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
