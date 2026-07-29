<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoAudit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Mirasvit\SeoAudit\Model\Config\Source\CrawlIntensity;
use Mirasvit\SeoAudit\Service\ServerLoadService;

class ConfigProvider
{
    private $scopeConfig;

    private $serverLoadService;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ServerLoadService $serverLoadService
    ) {
        $this->scopeConfig       = $scopeConfig;
        $this->serverLoadService = $serverLoadService;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        if ($storeId !== null) {
            return (bool)$this->scopeConfig->getValue(
                'seo_audit/general/is_enabled',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }

        return (bool)$this->scopeConfig->getValue('seo_audit/general/is_enabled');
    }

    public function getServerLoadThreshold(): int
    {
        switch ($this->getCrawlIntensityLevel()) {
            case CrawlIntensity::LEVEL_HIGH:
                return 90;
            case CrawlIntensity::LEVEL_MEDIUM:
                return 80;
            case CrawlIntensity::LEVEL_LOW:
                return 60;
            default:
                return (int)$this->scopeConfig->getValue('seo_audit/performance/server_load_threshold');
        }
    }

    public function shouldRunAudit(): bool
    {
        return $this->isEnabled() && $this->getServerLoadRate() <= $this->getServerLoadThreshold();
    }

    public function getServerLoadRate(): int
    {
        return $this->serverLoadService->getRate();
    }

    /**
     * @return string[] URL substrings to skip during crawl.
     */
    public function getExcludePatterns(): array
    {
        $value = (string)$this->scopeConfig->getValue('seo_audit/general/exclude_patterns');

        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $value))));
    }

    public function getMaxJobLifetimeDays(): int
    {
        switch ($this->getCrawlIntensityLevel()) {
            case CrawlIntensity::LEVEL_HIGH:
                return 1;
            case CrawlIntensity::LEVEL_MEDIUM:
                return 1;
            case CrawlIntensity::LEVEL_LOW:
                return 2;
            default:
                return max(1, (int)$this->scopeConfig->getValue('seo_audit/performance/max_job_lifetime_days'));
        }
    }

    public function getCrawlIntensityLevel(): int
    {
        return (int)$this->scopeConfig->getValue('seo_audit/performance/level');
    }

    /**
     * Delay in milliseconds between individual URL requests.
     * Preset values: High=0, Medium=200, Low=500, Custom=admin-defined.
     */
    public function getCrawlDelay(): int
    {
        switch ($this->getCrawlIntensityLevel()) {
            case CrawlIntensity::LEVEL_HIGH:
                return 0;
            case CrawlIntensity::LEVEL_MEDIUM:
                return 200;
            case CrawlIntensity::LEVEL_LOW:
                return 500;
            default:
                return (int)$this->scopeConfig->getValue('seo_audit/performance/delay');
        }
    }

    /**
     * Maximum URLs to crawl per cron run. 0 means unlimited (time-bound only).
     * Only honoured in Custom intensity mode.
     */
    public function getMaxUrlsPerRun(): int
    {
        if ($this->getCrawlIntensityLevel() === CrawlIntensity::LEVEL_CUSTOM) {
            return (int)$this->scopeConfig->getValue('seo_audit/performance/max_urls_per_run');
        }

        return 0;
    }
}
