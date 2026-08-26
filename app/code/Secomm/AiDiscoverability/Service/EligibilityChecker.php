<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service;

/**
 * Route-level eligibility: only public, non-session, non-variant internal
 * paths may ever be emitted (SPEC-TASK-0X552E §4.5). Fixed in code on purpose —
 * this is baseline safety policy, not merchant configuration.
 */
class EligibilityChecker
{
    /**
     * Route prefixes that must never appear in llms.txt.
     */
    private const BLOCKED_PREFIXES = [
        'admin',
        'api',
        'rest',
        'graphql',
        'checkout',
        'cart',
        'customer',
        'account',
        'wishlist',
        'catalogsearch',
        'product_compare',
        'compare',
        'review',
        'oauth',
        'llms',
        'llms.txt',
        'robots.txt',
    ];

    /**
     * Magento utility/system CMS identifiers that must never be emitted, even
     * if mistakenly selected by an admin (SPEC-TASK-7FBHHC §2.2). Exact match
     * only — deliberately NOT a pattern list, so legitimate merchant CMS pages
     * with other identifiers are unaffected.
     */
    private const BLOCKED_CMS_IDENTIFIERS = [
        'enable-cookies',
        'no-route',
    ];

    /**
     * Whether a candidate internal path is eligible (internal, bare, unblocked).
     *
     * @param string $path candidate internal path
     * @return bool true when the path may be emitted
     */
    public function isEligiblePath(string $path): bool
    {
        if ($path === '' || $path === '/') {
            return true; // home page
        }

        if (str_contains($path, '?') || str_contains($path, '#') || str_contains($path, '://')) {
            return false;
        }

        $normalized = ltrim($path, '/');
        $firstSegment = strtok($normalized, '/') ?: '';

        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (strcasecmp($firstSegment, $prefix) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a CMS page identifier is eligible for emission.
     *
     * @param string $identifier CMS page identifier
     * @return bool true when the page may be emitted
     */
    public function isEligibleCmsIdentifier(string $identifier): bool
    {
        $identifier = trim(strtolower($identifier));

        return !in_array($identifier, self::BLOCKED_CMS_IDENTIFIERS, true);
    }
}
