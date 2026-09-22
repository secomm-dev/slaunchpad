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
final class PhoneNormalizer
{
    private const VN_COUNTRY_CODE = '84';

    /**
     * +84 followed by 9–10 digits (VN mobile = 9 after country code,
     * landline with area code = 10).
     */
    private const VN_SUBSCRIBER_LENGTHS = [9, 10];

    private const MIN_DIGITS = 7;

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

        if (!in_array(strlen($subscriber), self::VN_SUBSCRIBER_LENGTHS, true)) {
            return null;
        }

        return '+' . self::VN_COUNTRY_CODE . $subscriber;
    }
}