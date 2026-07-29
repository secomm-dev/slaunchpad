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

namespace Mirasvit\SeoAi\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\Manager;
use Magento\Store\Model\ScopeInterface;
use Mirasvit\Core\Ai\Model\ConfigProvider as CoreConfigProvider;

class ConfigProvider
{
    public const XML_PATH_USE_CORE_AI = 'seo_ai/general/use_core_ai';
    public const XML_PATH_PROVIDER    = 'seo_ai/general/provider';

    private $scopeConfig;

    private $encryptor;

    private $moduleManager;

    private $coreConfigProvider;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface   $encryptor,
        Manager              $moduleManager,
        CoreConfigProvider   $coreConfigProvider
    ) {
        $this->scopeConfig        = $scopeConfig;
        $this->encryptor          = $encryptor;
        $this->moduleManager      = $moduleManager;
        $this->coreConfigProvider = $coreConfigProvider;
    }

    public function isHelperEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('seo_ai/general/is_enabled');
    }

    public function isUpdateRewrites(): bool
    {
        return $this->scopeConfig->isSetFlag('seo_audit/ai_helper/is_update');
    }

    public function isIncludeStoreData(): bool
    {
        return $this->scopeConfig->isSetFlag('seo_audit/ai_helper/is_include_store');
    }

    public function useCoreAi(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_USE_CORE_AI);
    }

    public function getProvider(): string
    {
        if ($this->useCoreAi()) {
            return $this->coreConfigProvider->getDefaultProvider();
        }

        return (string)$this->scopeConfig->getValue(self::XML_PATH_PROVIDER) ?: CoreConfigProvider::PROVIDER_OPENAI;
    }

    public function getApiKey(): ?string
    {
        $provider = $this->getProvider();

        if ($this->useCoreAi()) {
            $key = $this->coreConfigProvider->getApiKey($provider);

            return $key ?: null;
        }

        $key = $this->scopeConfig->getValue('seo_ai/general/' . $provider . '_key');

        return $key ? $this->encryptor->decrypt($key) : null;
    }

    public function getModel(): string
    {
        $provider = $this->getProvider();

        if ($this->useCoreAi()) {
            return $this->coreConfigProvider->getDefaultModel($provider);
        }

        return (string)$this->scopeConfig->getValue('seo_ai/general/' . $provider . '_model') ?: 'gpt-3.5-turbo';
    }

    public function getCantRunReasons(): array
    {
        $reasons = [];

        if (!$this->moduleManager->isEnabled('Mirasvit_SeoAudit')) {
            $reasons[] = 'Mirasvit_SeoAudit module disabled.';
        }

        if (!$this->moduleManager->isEnabled('Mirasvit_SeoContent')) {
            $reasons[] = 'Mirasvit_SeoContent module disabled.';
        }

        if (!$this->isHelperEnabled()) {
            $reasons[] = 'SEO AI Helper disabled.';
        }

        if (!$this->scopeConfig->isSetFlag('seo_audit/general/is_enabled')) {
            $reasons[] = 'SEO Audit is disabled.';
        }

        if (!$this->scopeConfig->getValue('seo_audit/ai_helper/is_auto_fix_meta')) {
            $reasons[] = 'The feature is disable in the configurations of the Mirasvit_SeoAudit module.';
        }

        if (!$this->getApiKey()) {
            $reasons[] = 'API key is not configured.';
        }

        return $reasons;
    }

    public function getAdditionalStoreData(): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_audit/ai_helper/store_description',
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function isCronEnabled(): bool
    {
        // Gate the scheduled job on the same "can run" seam the CLI command and
        // admin validation use (getCantRunReasons), so disabling the feature
        // (is_auto_fix_meta / seo_audit/general/is_enabled / SEO AI Helper) or an
        // unconfigured API key stops the cron instead of only the manual paths.
        return $this->isUpdateRewrites()
            && $this->scopeConfig->isSetFlag('seo_audit/ai_helper/is_cron')
            && count($this->getCantRunReasons()) === 0;
    }

    public function getLanguage(int $storeId): string
    {
        return \Locale::getDisplayLanguage($this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORES,
            $storeId
        )) ?: '';
    }
}
