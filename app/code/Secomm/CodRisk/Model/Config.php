<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * COD Risk configuration reader (website scope aware).
 */
class Config
{
    public const XML_PATH_ENABLED = 'codrisk/general/enabled';
    public const XML_PATH_LOOKBACK_DAYS = 'codrisk/historical/lookback_days';
    public const XML_PATH_WARNING_THRESHOLD = 'codrisk/historical/warning_threshold';
    public const XML_PATH_BLOCK_THRESHOLD = 'codrisk/historical/block_threshold';
    public const XML_PATH_SPAM_ENABLED = 'codrisk/spam/enabled';
    public const XML_PATH_SPAM_LOOKBACK_DAYS = 'codrisk/spam/lookback_days';
    public const XML_PATH_SPAM_THRESHOLD = 'codrisk/spam/threshold';
    public const XML_PATH_SPAM_ATTRIBUTABLE_REASONS = 'codrisk/spam/attributable_reasons';
    public const XML_PATH_CHECKOUT_MESSAGE = 'codrisk/checkout/message';
    public const XML_PATH_REASON_INCLUDE_PREFIX = 'codrisk/reasons/include_';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function isEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    public function getLookbackDays(?int $websiteId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_LOOKBACK_DAYS, $websiteId, 180));
    }

    public function getWarningThreshold(?int $websiteId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_WARNING_THRESHOLD, $websiteId, 2));
    }

    public function getBlockThreshold(?int $websiteId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_BLOCK_THRESHOLD, $websiteId, 3));
    }

    public function isSpamEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SPAM_ENABLED,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    public function getSpamLookbackDays(?int $websiteId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_SPAM_LOOKBACK_DAYS, $websiteId, 2));
    }

    public function getSpamThreshold(?int $websiteId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_SPAM_THRESHOLD, $websiteId, 10));
    }

    /**
     * @return string[] Reason codes counted as customer-attributable spam behavior.
     */
    public function getSpamAttributableReasons(?int $websiteId = null): array
    {
        $raw = (string)$this->getValue(
            self::XML_PATH_SPAM_ATTRIBUTABLE_REASONS,
            $websiteId,
            'CUSTOMER_CANCELLATION_PRE_CONFIRM,REFUSED_DELIVERY,UNREACHABLE_CUSTOMER'
        );

        $codes = array_filter(array_map('trim', explode(',', $raw)));

        return $codes ?: ['CUSTOMER_CANCELLATION_PRE_CONFIRM', 'REFUSED_DELIVERY', 'UNREACHABLE_CUSTOMER'];
    }

    public function getCheckoutMessage(?int $websiteId = null): string
    {
        return (string)$this->getValue(
            self::XML_PATH_CHECKOUT_MESSAGE,
            $websiteId,
            'COD is currently unavailable for this order. Please choose another payment method.'
        );
    }

    /**
     * Three-state include flag: null = not configured (catalog default applies),
     * false/true = explicit admin decision for the website scope.
     */
    public function getReasonIncludeFlag(string $reasonCode, ?int $websiteId = null): ?bool
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_REASON_INCLUDE_PREFIX . strtolower($reasonCode),
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );

        if ($value === null) {
            return null;
        }

        return (bool)(int)$value;
    }

    private function getValue(string $path, ?int $websiteId, string|int $default): string|int
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE, $websiteId);

        return $value === null ? $default : $value;
    }
}
