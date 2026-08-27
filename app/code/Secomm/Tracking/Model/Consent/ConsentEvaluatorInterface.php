<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Consent;

/**
 * FEAT-31X6N2 — consent gate contract. Phase 1 implementation: Cookie Restriction
 * Mode (DEC D5). A future CMP swaps the implementation in di.xml only.
 */
interface ConsentEvaluatorInterface
{
    public const SCOPE_ANALYTICS = 'analytics';
    public const SCOPE_MARKETING = 'marketing';

    /**
     * @param string $scope self::SCOPE_*
     * @param string|null $scopeCode store scope code
     * @return bool true when events of this scope may be dispatched
     */
    public function allows(string $scope, ?string $scopeCode = null): bool;
}
