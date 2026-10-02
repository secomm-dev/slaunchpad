<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Phone;

/**
 * VN phone normalization to E.164 (+84XXXXXXXXX).
 *
 * Risk identity: different common VN input formats must map to ONE identity
 * (spec nguồn §5.2). Behavior reference: Secomm_Tracking UserDataHasher::normalizePhone()
 * (copied semantics, no module dependency — module boundary, SPEC-TASK-YPWH9B §5.2).
 *
 * Invalid/unusable input returns null — an invalid phone is an address/checkout
 * validation concern, never a risk BLOCK (CR-008).
 */
class PhoneNormalizer
{
    private const VN_COUNTRY_CODE = '84';

    private const MIN_DIGITS = 7;

    /**
     * VN numbering plan (post 2018 migration):
     * - mobile:      9 digits after +84, first digit 3/5/7/8/9
     * - landline:    9 digits starting 2 (area 2xx + 7 digits), or
     *                10 digits starting 2 (long area codes)
     * A "mobile-looking" subscriber with 10 digits (QC case 01/10 — one
     * typed digit too many) must NOT normalize into a valid identity.
     */
    private const MOBILE_FIRST_DIGITS = '35789';
    private const LANDLINE_FIRST_DIGIT = '2';

    public function normalize(?string $phone): ?string
    {
        $trimmed = trim((string)$phone);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if (strlen($digits) < self::MIN_DIGITS) {
            return null;
        }

        // Local VN format: leading trunk 0 — drop it in front of the country code.
        if ($digits[0] === '0') {
            $subscriber = substr($digits, 1);
        } elseif (str_starts_with($digits, self::VN_COUNTRY_CODE)) {
            $subscriber = substr($digits, strlen(self::VN_COUNTRY_CODE));
        } else {
            // Non-VN input: outside P1 scope — not a usable VN risk identity.
            return null;
        }

        $length = strlen($subscriber);
        $first = $subscriber[0];
        $valid = ($length === 9 && ($first === self::LANDLINE_FIRST_DIGIT || str_contains(self::MOBILE_FIRST_DIGITS, $first)))
            || ($length === 10 && $first === self::LANDLINE_FIRST_DIGIT);
        if (!$valid) {
            return null;
        }

        return '+' . self::VN_COUNTRY_CODE . $subscriber;
    }
}