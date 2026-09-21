<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Logger;

use Psr\Log\LoggerInterface;

/**
 * SPEC-FEAT-FQWEQ3 §48 — GHN context logger. Wraps the module's dedicated Monolog channel and
 * enforces token hygiene: every context passes through sanitizeContext() before being written.
 *
 * Scrubbed keys: anything whose name contains "token" (api_token, Token header…) plus the GHN
 * auth headers. Values are replaced with a fixed marker — length-preserving obfuscation is not a
 * security property, a boolean-ish marker is enough for debugging.
 */
final class GhnLogger
{
    /** Marker written instead of scrubbed values. */
    public const SCRUBBED = '[scrubbed]';

    /** Context key fragments (case-insensitive contains) whose value is always removed. */
    private const SENSITIVE_KEYS = ['token', 'shopid', 'authorization'];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * Standard one-line context for a GHN API call (operation, shop_id, http_status,
     * provider_code, duration_ms) — built here so every call site logs the same shape.
     *
     * @param array<string, mixed> $context
     */
    public function call(string $message, array $context): void
    {
        $this->logger->info($message, self::sanitizeContext($context));
    }

    /**
     * Warning-level context (technical failures, degraded configuration — rate hidden but
     * checkout continues).
     *
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context): void
    {
        $this->logger->warning($message, self::sanitizeContext($context));
    }

    /**
     * Error-level context (unexpected failures caught at carrier boundaries — estimation
     * must never crash, so the error is recorded and the method hidden).
     *
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context): void
    {
        $this->logger->error($message, self::sanitizeContext($context));
    }

    /**
     * Debug-level payload logging. Callers MUST gate this behind the debug config themselves;
     * the payload still passes the scrubber.
     *
     * @param array<string, mixed> $payload
     */
    public function debugPayload(string $message, array $payload): void
    {
        $this->logger->debug($message, self::sanitizeContext($payload));
    }

    /**
     * Removes sensitive values from a context array (recursively, one level of nesting covered).
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function sanitizeContext(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $clean[$key] = self::SCRUBBED;
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = self::sanitizeContext($value);
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (stripos($key, $sensitive) !== false) {
                return true;
            }
        }

        return false;
    }
}
