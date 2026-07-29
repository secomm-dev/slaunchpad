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

namespace Mirasvit\SeoToolbar\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    private $scopeConfig;

    private $request;

    private $storeManager;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        RequestInterface $request,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig  = $scopeConfig;
        $this->request      = $request;
        $this->storeManager = $storeManager;
    }

    public function isToolbarAllowed(): bool
    {
        if ($this->request->getParam('debug') && $this->request->getParam('debug') == 'seo') {
            return true;
        }

        if (!$this->isToolbarActive()) {
            return false;
        }

        $ips = $this->getAllowedIps();

        if ($ips == '') {
            return true;
        }

        $ips = explode(',', $ips);
        $ips = array_map('trim', $ips);

        $keys = ['REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP'];

        foreach ($keys as $key) {
            if (isset($_SERVER[$key]) && in_array($_SERVER[$key], $ips)) {
                return true;
            }
        }

        return false;
    }

    public function isToolbarActive(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'seo/seo_toolbar/is_active',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );
    }

    public function getAllowedIps(): string
    {
        return (string)$this->scopeConfig->getValue(
            'seo/seo_toolbar/allowed_ip',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );
    }

    public function getDisableLinks(?int $storeId = null): array
    {
        $links = ['checkout', 'customer', 'robots.txt', 'ajax', 'mstseoai'];

        $conf = (string)$this->scopeConfig->getValue(
            'seo/seo_toolbar/disable_links',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $customLinks = explode("\n", trim($conf));
        $customLinks = array_map('trim', $customLinks);
        $customLinks = array_diff($customLinks, [0, null]);

        $links = array_merge($links, $customLinks);

        return $links;
    }
}
