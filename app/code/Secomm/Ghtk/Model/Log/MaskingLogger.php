<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Log;

use Psr\Log\LoggerInterface;

/**
 * PSR-3 wrapper that redacts sensitive values in $context before delegating to the
 * framework logger. M2's built-in AbstractCarrier debug masking is XML-only, so this
 * handles the JSON/array payloads GHTK produces (AC-013).
 *
 * Production log line carries only: correlation_id, Magento IDs, HTTP status, GHTK
 * error code/message, masked destination, retry attempt — never raw token or address.
 */
class MaskingLogger
{
    /** Keys (case-insensitive, substring match) whose values are redacted. */
    private const SENSITIVE_KEYS = [
        'token', 'password', 'secret', 'apikey', 'api_key',
        'phone', 'tel', 'mobile', 'email', 'address', 'street', 'name', 'fullname',
        'province', 'ward', 'district', 'pick_',
    ];

    private const MASK = '****';

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $this->mask($context));
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $this->mask($context));
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $this->mask($context));
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $this->mask($context));
    }

    /**
     * Recursively redact sensitive values by key (substring, case-insensitive).
     */
    public function mask(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $out[$key] = self::MASK;
            } elseif (is_array($value)) {
                $out[$key] = $this->mask($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
