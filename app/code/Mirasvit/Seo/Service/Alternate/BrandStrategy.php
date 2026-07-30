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

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Api\Service\Alternate\StrategyInterface;
use Mirasvit\Seo\Api\Service\Alternate\UrlInterface;

class BrandStrategy implements StrategyInterface
{
    private $moduleManager;

    private $url;

    private $storeManager;

    public function __construct(
        Manager               $moduleManager,
        UrlInterface          $url,
        StoreManagerInterface $storeManager
    ) {
        $this->moduleManager = $moduleManager;
        $this->url           = $url;
        $this->storeManager  = $storeManager;
    }

    public function getStoreUrls(): array
    {
        if (!$this->moduleManager->isEnabled('Mirasvit_Brand')) {
            return [];
        }

        $brandRegistry = ObjectManager::getInstance()->get('\Mirasvit\Brand\Registry');

        if (!$brandRegistry) {
            return [];
        }

        $storeUrls = $this->url->getStoresCurrentUrl();

        if (!$storeUrls) {
            return [];
        }

        $request = ObjectManager::getInstance()->get('\Magento\Framework\App\Request\Http');
        $brand   = $brandRegistry->getBrand();

        // we are on a brand page but the brand is not in the registry yet
        // alternates will be added when this method is called the second time
        if (!$brand && $request->getActionName() == 'view') {
            return [];
        }

        return $this->getBrandAlternates($storeUrls, $brand);
    }

    public function getAlternateUrl(array $storeUrls, ?int $entityId = null, ?int $storeId = null): array
    {
        return [];
    }

    public function getBrandAlternates($storeUrls, $brand = null): array
    {
        $urls = [];

        $brandUrlService = ObjectManager::getInstance()->get('Mirasvit\Brand\Service\BrandUrlService');

        if (!$brandUrlService) {
            return $urls;
        }

        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int)$store->getId();

            if ($brand) {
                $urls[$storeId] = $storeUrls[$storeId] ?? $brandUrlService->getBrandUrl($brand, $storeId);
            } else {
                $urls[$storeId] = $brandUrlService->getBaseBrandUrl($storeId);
            }
        }

        return $this->url->processStoreParamForDuplicates($urls);
    }
}
