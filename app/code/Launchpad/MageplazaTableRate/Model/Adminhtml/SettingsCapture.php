<?php
/*
 * TASK-SEC-C1 — request-scoped capture of the "Fallback Settings" tab payload.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model\Adminhtml;

/**
 * Bridges the controller seam (where the `launchpad[...]` HTTP payload arrives) to the
 * resource seam (where the persisted method identity is known). DI singleton = per-request
 * lifetime: two admins creating methods concurrently run two PHP request scopes, so a
 * capture can never be applied to another admin's method — no MAX(id) guess, no session.
 */
class SettingsCapture
{
    private ?array $pending = null;

    /**
     * Remember the posted payload for the ONE save this request is performing.
     */
    public function capture(array $payload): void
    {
        $this->pending = $payload;
    }

    /**
     * Return and CLEAR the pending payload (exactly-once semantics — a later unrelated
     * save in the same request can never inherit it).
     */
    public function consume(): ?array
    {
        $pending = $this->pending;
        $this->pending = null;

        return $pending;
    }
}
