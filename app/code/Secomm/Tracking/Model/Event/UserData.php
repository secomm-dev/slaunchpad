<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Event;

/**
 * FEAT-31X6N2 / SPEC-FEAT-31X6N2 §4, §6.4 — user block of a TrackingEvent.
 *
 * [BLOCK] PII rule: email/phone/externalId arrive here ONLY as SHA-256 hex from
 * UserDataHasher — the normalizer never constructs this DTO with plain values.
 * fbp/fbc/ttclid/ttp are browser cookie params carried through for matching.
 * clientIp/clientUserAgent come from server request/order remote data.
 */
final class UserData
{
    /**
     * @param string|null $emailSha256 SHA-256 hex of normalized (trim+lowercase) email
     * @param string|null $phoneSha256 SHA-256 hex of E.164-normalized phone
     * @param string|null $externalIdSha256 SHA-256 hex of customer id
     * @param string|null $fbp Meta _fbp cookie
     * @param string|null $fbc Meta _fbc cookie
     * @param string|null $ttclid TikTok click id
     * @param string|null $ttp TikTok _ttp cookie
     * @param string|null $clientIp Client IP (masked before any logging)
     * @param string|null $clientUserAgent Client user agent
     */
    public function __construct(
        public readonly ?string $emailSha256,
        public readonly ?string $phoneSha256,
        public readonly ?string $externalIdSha256,
        public readonly ?string $fbp = null,
        public readonly ?string $fbc = null,
        public readonly ?string $ttclid = null,
        public readonly ?string $ttp = null,
        public readonly ?string $clientIp = null,
        public readonly ?string $clientUserAgent = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'email_sha256' => $this->emailSha256,
            'phone_sha256' => $this->phoneSha256,
            'external_id_sha256' => $this->externalIdSha256,
            'fbp' => $this->fbp,
            'fbc' => $this->fbc,
            'ttclid' => $this->ttclid,
            'ttp' => $this->ttp,
            'client_ip' => $this->clientIp,
            'client_user_agent' => $this->clientUserAgent,
        ];

        // Absent cookie params stay absent (never empty strings) — TASK-E0NG8Z AC-3.
        return array_filter($data, static fn ($v) => $v !== null && $v !== '');
    }
}
