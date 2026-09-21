<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — immutable per-method capability VO.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model\Data;

/**
 * Per-Mageplaza-method Launchpad capabilities. No settings row = native Mageplaza behavior
 * (visible as configured by Mageplaza itself, never a fallback group) — the default keeps every
 * pre-existing method zero-regression.
 */
final class MethodSettings
{
    public function __construct(
        private readonly int $methodId,
        private readonly bool $showToCustomer,
        private readonly bool $useAsFallback
    ) {
    }

    public function getMethodId(): int
    {
        return $this->methodId;
    }

    public function isShowToCustomer(): bool
    {
        return $this->showToCustomer;
    }

    public function isUseAsFallback(): bool
    {
        return $this->useAsFallback;
    }
}
