<?php
/**
 * Footer legal links view model (TASK-KMJV5Q, SLP-291).
 *
 * Resolves the label/URL of the two copyright-bar legal links (Terms &
 * Privacy) from system config `launchpad_footer/legal_links/*` (store view
 * scope). A null return means "not configured" — the copyright template then
 * falls back to the translated default phrase / static route shipped in
 * TASK-7EYJ4C (SLP-275), so an empty config keeps the footer byte-equal to
 * the pre-config behavior.
 *
 * URL rule: a configured value starting with http(s):// is used as-is
 * (external links); anything else is treated as a route/path and resolved
 * through UrlInterface.
 *
 * @copyright Copyright (c) 2026 Secomm. (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\CmsContent\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

final class LegalLinks implements ArgumentInterface
{
    private const CONFIG_PATH_TERMS_LABEL = 'launchpad_footer/legal_links/terms_label';
    private const CONFIG_PATH_TERMS_URL = 'launchpad_footer/legal_links/terms_url';
    private const CONFIG_PATH_PRIVACY_LABEL = 'launchpad_footer/legal_links/privacy_label';
    private const CONFIG_PATH_PRIVACY_URL = 'launchpad_footer/legal_links/privacy_url';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $url
    ) {
    }

    public function getTermsLabel(): ?string
    {
        return $this->getConfigText(self::CONFIG_PATH_TERMS_LABEL);
    }

    public function getTermsUrl(): ?string
    {
        return $this->resolveUrl(self::CONFIG_PATH_TERMS_URL);
    }

    public function getPrivacyLabel(): ?string
    {
        return $this->getConfigText(self::CONFIG_PATH_PRIVACY_LABEL);
    }

    public function getPrivacyUrl(): ?string
    {
        return $this->resolveUrl(self::CONFIG_PATH_PRIVACY_URL);
    }

    /**
     * Configured text for the current store; null when empty (fallback case).
     */
    private function getConfigText(string $path): ?string
    {
        $value = trim((string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE));

        return $value === '' ? null : $value;
    }

    /**
     * Full http(s) URLs pass through; anything else resolves as a route/path.
     */
    private function resolveUrl(string $path): ?string
    {
        $value = $this->getConfigText($path);
        if ($value === null) {
            return null;
        }
        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        return $this->url->getUrl($value);
    }
}
