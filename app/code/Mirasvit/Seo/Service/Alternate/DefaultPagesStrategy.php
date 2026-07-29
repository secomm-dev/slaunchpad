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

namespace Mirasvit\Seo\Service\Alternate;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Api\Service\Alternate\StrategyInterface;
use Mirasvit\Seo\Api\Service\Alternate\UrlInterface as AlternateUrlInterface;

class DefaultPagesStrategy implements StrategyInterface
{
    protected $url;

    protected $urlInterface;

    protected $storeManager;

    public function __construct(
        AlternateUrlInterface $url,
        UrlInterface          $urlInterface,
        StoreManagerInterface $storeManager
    ) {
        $this->url          = $url;
        $this->urlInterface = $urlInterface;
        $this->storeManager = $storeManager;
    }

    public function getStoreUrls(): array
    {
        $storeUrls = $this->url->getStoresCurrentUrl();
        // To prevent "Exception #0 (Exception): Warning: Invalid argument supplied for foreach()" for some stores BEGIN
        if (!$storeUrls) {
            return [];
        }
        // To prevent "Exception #0 (Exception): Warning: Invalid argument supplied for foreach()" for some stores END
        $storeUrls = $this->getAlternateUrl($storeUrls);

        return $storeUrls;
    }

    public function getAlternateUrl(array $storeUrls, ?int $entityId = null, ?int $storeId = null): array
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $currentUrl = $this->urlInterface->getCurrentUrl();
        $currentPath = strtok($currentUrl, '?') ?: '';
        $currentPath = str_replace($baseUrl, '', $currentPath);
        $currentPath = strstr($currentPath, 'referer/', true); //prepare customer/account page
        foreach ($storeUrls as $key => $storeUrl) {
            $storeUrls[$key] =  strtok($storeUrl, '?') . $currentPath;
        }

        return $this->url->processStoreParamForDuplicates($storeUrls);
    }
}
