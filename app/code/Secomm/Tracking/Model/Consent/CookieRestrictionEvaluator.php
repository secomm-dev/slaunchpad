<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Consent;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Stdlib\Cookie\CookieReaderInterface;

/**
 * FEAT-31X6N2 / DEC-FEAT31X6N2-001 D5 — Phase 1 consent gate.
 *
 * Uses Magento Cookie Restriction Mode: when the restriction is OFF, consent is
 * granted (default VN market). When ON, marketing consent requires the
 * user_allowed_save_cookie cookie set by the native restriction notice.
 */
class CookieRestrictionEvaluator implements ConsentEvaluatorInterface
{
    private const XML_PATH_COOKIE_RESTRICTION = 'web/cookie/cookie_restriction';

    private const ALLOWED_COOKIE = 'user_allowed_save_cookie';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CookieReaderInterface $cookieReader
    ) {
    }

    /**
     * Analytics follows the same gate in Phase 1 — a dedicated analytics-only
     * state arrives with a real CMP (out of scope, DEC D5).
     */
    public function allows(string $scope, ?string $scopeCode = null): bool
    {
        $restrictionOn = $this->scopeConfig->isSetFlag(
            self::XML_PATH_COOKIE_RESTRICTION,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $scopeCode
        );

        if (!$restrictionOn) {
            return true;
        }

        return $this->cookieReader->getCookie(self::ALLOWED_COOKIE) !== null;
    }
}
