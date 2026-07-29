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

namespace Mirasvit\SeoSitemap\Repository\Provider\Magento;

use Magento\Framework\DataObject;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoSitemap\Model\ItemProvider\InspectableComposite;
use Mirasvit\SeoSitemap\Repository\Provider\AbstractProvider;

class CompositeProvider extends AbstractProvider
{
    const KEY         = 'other';
    const MODULE_NAME = 'Magento_Sitemap';
    const TITLE       = 'Other';

    private $composite;

    private $storeManager;

    private $excludedProviderKeys;

    public function __construct(
        InspectableComposite  $composite,
        StoreManagerInterface $storeManager,
        array                 $excludedProviderKeys = []
    ) {
        $this->composite            = $composite;
        $this->storeManager         = $storeManager;
        $this->excludedProviderKeys = $excludedProviderKeys;
    }

    public function getItems($storeId): array
    {
        $providers = array_diff_key(
            $this->composite->getProviders(),
            array_flip($this->excludedProviderKeys)
        );

        if (empty($providers)) {
            return [];
        }

        $baseUrl = rtrim($this->storeManager->getStore($storeId)->getBaseUrl(), '/') . '/';

        $items = [];
        foreach ($providers as $provider) {
            foreach ($provider->getItems($storeId) as $sitemapItem) {
                $url = $sitemapItem->getUrl();

                if (strpos($url, 'http') !== 0) {
                    $url = $baseUrl . ltrim($url, '/');
                }

                $items[] = new DataObject([
                    'url'        => $url,
                    'title'      => $url,
                    'updated_at' => $sitemapItem->getUpdatedAt(),
                ]);
            }
        }

        return $items;
    }

    public function initSitemapItem($storeId): array
    {
        $providers = array_diff_key(
            $this->composite->getProviders(),
            array_flip($this->excludedProviderKeys)
        );

        if (empty($providers)) {
            return [];
        }

        $baseUrl = rtrim($this->storeManager->getStore($storeId)->getBaseUrl(), '/') . '/';

        $result = [];
        foreach ($providers as $provider) {
            foreach ($provider->getItems($storeId) as $sitemapItem) {
                $url = $sitemapItem->getUrl();

                if (strpos($url, 'http') !== 0) {
                    $url = $baseUrl . ltrim($url, '/');
                }

                $result[] = new DataObject([
                    'changefreq' => $sitemapItem->getChangeFrequency(),
                    'priority'   => $sitemapItem->getPriority(),
                    'collection' => [new DataObject([
                        'url'        => $url,
                        'updated_at' => $sitemapItem->getUpdatedAt(),
                    ])],
                ]);
            }
        }

        return $result;
    }
}
