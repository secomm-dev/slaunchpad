<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * FEAT-CSWYEJ — typed read access to the secomm_paymentcore config tree (spec §4.6).
 *
 * Expiry resolution (DEC D4): per-method override beats default; invalid override
 * lines are ignored with a warning log (never fail checkout for bad config).
 */
class Config
{
    public const XML_PATH_ENABLED = 'secomm_paymentcore/general/enabled';
    public const XML_PATH_MANAGED_METHODS = 'secomm_paymentcore/general/managed_methods';
    public const XML_PATH_DEFAULT_EXPIRY = 'secomm_paymentcore/general/default_expiry_minutes';
    public const XML_PATH_EXPIRY_OVERRIDES = 'secomm_paymentcore/general/expiry_overrides';
    public const XML_PATH_CONTINUE_DISABLED = 'secomm_paymentcore/general/continue_disabled';
    public const XML_PATH_CRON_BATCH_SIZE = 'secomm_paymentcore/cron/batch_size';
    public const XML_PATH_CRON_FORCE_CLOSE_DAYS = 'secomm_paymentcore/cron/force_close_days';

    /**
     * DEC-FEATCSWYEJ-003 — aligned with the VNPAY payment-session TTL (15 min
     * on sandbox/prod). The window is measured from the LAST checkout-URL
     * generation (place order or Continue Payment), because every generation
     * creates a fresh provider session; refresh happens in the retry controller.
     */
    public const DEFAULT_EXPIRY_MINUTES = 15;
    public const DEFAULT_BATCH_SIZE = 50;
    public const DEFAULT_FORCE_CLOSE_DAYS = 7;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Master switch. Snapshot semantics (DEC D5): disabling stops NEW records only;
     * the expiry cron keeps draining existing active records — it deliberately does
     * NOT read this flag.
     */
    public function isEnabled(int|string|null $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_WEBSITE, $scopeCode);
    }

    /**
     * Payment method codes the core manages, at website scope.
     *
     * @return string[]
     */
    public function getManagedMethods(int|string|null $scopeCode = null): array
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_MANAGED_METHODS, ScopeInterface::SCOPE_WEBSITE, $scopeCode);
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($code) => $code !== ''));
    }

    public function isManagedMethod(string $methodCode, int|string|null $scopeCode = null): bool
    {
        return in_array($methodCode, $this->getManagedMethods($scopeCode), true);
    }

    /**
     * Continue Payment is off for this method (per-method toggle, spec §4.6).
     */
    public function isContinueDisabled(string $methodCode, int|string|null $scopeCode = null): bool
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_CONTINUE_DISABLED,
            ScopeInterface::SCOPE_WEBSITE,
            $scopeCode
        );
        if (!is_string($value) || trim($value) === '') {
            return false;
        }
        $disabled = array_map('trim', explode(',', $value));
        return in_array($methodCode, $disabled, true);
    }

    /**
     * Resolve expiry minutes for a method: override > default (DEC D4).
     */
    public function resolveExpiryMinutes(string $methodCode, int|string|null $scopeCode = null): int
    {
        $override = $this->getExpiryOverrides($scopeCode)[$methodCode] ?? null;
        if ($override !== null && $override > 0) {
            return $override;
        }
        $default = (int)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_EXPIRY,
            ScopeInterface::SCOPE_WEBSITE,
            $scopeCode
        );
        return $default > 0 ? $default : self::DEFAULT_EXPIRY_MINUTES;
    }

    /**
     * Parse the expiry_overrides textarea ("method_code:minutes" per line).
     * Invalid lines are skipped with a warning (never fail checkout on bad config).
     *
     * @return array<string, int>
     */
    public function getExpiryOverrides(int|string|null $scopeCode = null): array
    {
        $raw = $this->scopeConfig->getValue(
            self::XML_PATH_EXPIRY_OVERRIDES,
            ScopeInterface::SCOPE_WEBSITE,
            $scopeCode
        );
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $overrides = [];
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode(':', $line);
            $isValid = count($parts) === 2
                && trim($parts[0]) !== ''
                && ctype_digit(trim($parts[1]))
                && (int)trim($parts[1]) > 0;
            if (!$isValid) {
                $this->logWarning(sprintf('Ignoring invalid expiry override line "%s"', $line));
                continue;
            }
            $overrides[trim($parts[0])] = (int)trim($parts[1]);
        }
        return $overrides;
    }

    public function getBatchSize(): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_CRON_BATCH_SIZE);
        return $value > 0 ? $value : self::DEFAULT_BATCH_SIZE;
    }

    public function getForceCloseDays(): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_CRON_FORCE_CLOSE_DAYS);
        return $value > 0 ? $value : self::DEFAULT_FORCE_CLOSE_DAYS;
    }

    private function logWarning(string $message): void
    {
        $this->logger?->warning('[PaymentCore] ' . $message);
    }
}
