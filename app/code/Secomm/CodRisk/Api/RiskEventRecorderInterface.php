<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Api;

use Secomm\CodRisk\Api\Data\RiskEventInterface;

/**
 * Entry point for recording normalized COD risk events.
 *
 * Sources (order lifecycle, admin/CS, carriers, imports) must normalize their
 * signal into an internal business reason and record through this contract —
 * they never write to CodRisk storage directly (spec nguồn §14).
 */
interface RiskEventRecorderInterface
{
    /**
     * Persists the event including an "include in historical count" snapshot taken
     * from the reason configuration at record time (spec nguồn §13).
     *
     * @param RiskEventInterface $event
     * @return void
     */
    public function record(RiskEventInterface $event): void;
}