<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Hash;

/**
 * FEAT-31X6N2 / SPEC-FEAT-31X6N2 §6.4 — normalization + SHA-256 for user identifiers.
 *
 * [BLOCK] PII rule: call sites pass server-side entity data (order billing address,
 * customer model) — never raw client input. The hasher returns hex digests only;
 * plain values must not be logged or persisted anywhere downstream.
 */
class UserDataHasher
{
    private const VN_COUNTRY_CODE = 'VN';

    private const VN_COUNTRY_CODES = ['VN', 'VNM'];

    /**
     * Email: trim + lowercase, then SHA-256 hex. Returns null when empty/unusable
     * so absent fields stay absent (never a hash of garbage).
     */
    public function hashEmail(?string $email): ?string
    {
        $normalized = trim((string)$email);
        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $this->sha256(strtolower($normalized));
    }

    /**
     * Phone: normalize to E.164 (VN-aware), then SHA-256 hex.
     *
     * VN rules (spec §6.4): strip separators; a 10-ish digit local number with a
     * leading 0 becomes +84{9-10 digits}. Numbers already carrying +84/84 keep
     * their digits. Other countries: strip separators, prepend "+" when a leading
     * country code is recognizable, otherwise leave digits as-is (best effort —
     * Meta matches on the digit string too).
     */
    public function hashPhone(?string $phone, ?string $countryCode = null): ?string
    {
        $e164 = $this->normalizePhone($phone, $countryCode);
        return $e164 === null ? null : $this->sha256($e164);
    }

    /**
     * External id (customer id): string form, then SHA-256 hex.
     */
    public function hashExternalId(int|string|null $customerId): ?string
    {
        $normalized = trim((string)$customerId);
        return $normalized === '' ? null : $this->sha256($normalized);
    }

    /**
     * Pure normalizer (exposed for unit testing the E.164 contract).
     */
    public function normalizePhone(?string $phone, ?string $countryCode = null): ?string
    {
        // Keep digits and a single leading plus; drop spaces, dashes, dots, parens.
        $trimmed = trim((string)$phone);
        if ($trimmed === '') {
            return null;
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        // Leading plus that produced no digits (e.g. "+") or a bare "+84" is unusable.
        if ($digits === '' || strlen($digits) < 7) {
            return null;
        }

        $isVn = $countryCode !== null
            && in_array(strtoupper(trim($countryCode)), self::VN_COUNTRY_CODES, true);

        $has84Prefix = str_starts_with($digits, '84') && strlen($digits) >= 10 && strlen($digits) <= 12;

        if ($isVn || $has84Prefix || $this->looksLikeVnLocal($digits, $isVn)) {
            return $this->toVnE164($digits);
        }

        // Already in international form (leading + or a plausible country code length).
        return $hasPlus ? '+' . $digits : $digits;
    }

    /**
     * VN local numbers: mobile 0{9 digits} (090…, 03x…), landline 0{8-10 digits}.
     */
    private function looksLikeVnLocal(string $digits, bool $isVnCountry): bool
    {
        if (str_starts_with($digits, '0')) {
            return strlen($digits) >= 10 && strlen($digits) <= 11;
        }

        // No leading zero and no 84 prefix — VN country context only, treat as local without trunk 0.
        return $isVnCountry && strlen($digits) >= 9 && strlen($digits) <= 10;
    }

    /**
     * Map digit forms to +84…: "84…"/"+84…" keeps the rest; "0…" drops the trunk
     * zero; bare local digits get +84 prepended.
     */
    private function toVnE164(string $digits): string
    {
        if (str_starts_with($digits, '84')) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '+84' . substr($digits, 1);
        }

        return '+84' . $digits;
    }

    /**
     * @throws \RuntimeException when ext-hash is unavailable (never in practice)
     */
    private function sha256(string $value): string
    {
        $hash = hash('sha256', $value);
        if ($hash === false) {
            throw new \RuntimeException('sha256 unavailable');
        }

        return $hash;
    }
}
