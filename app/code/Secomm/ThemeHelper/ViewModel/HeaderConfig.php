<?php

declare(strict_types=1);

namespace Secomm\ThemeHelper\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

final class HeaderConfig implements ArgumentInterface
{
    private const XML_PATH_STICKY_ENABLED = 'secomm_theme/header/sticky_enabled';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isStickyEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_STICKY_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }
}
