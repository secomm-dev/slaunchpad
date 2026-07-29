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



namespace Mirasvit\SeoSitemap\Repository\Provider\Mirasvit;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\DataObject;
use Mirasvit\SeoSitemap\Repository\Provider\AbstractProvider;

class KbProvider extends AbstractProvider
{
    const KEY         = 'knowledge_base';
    const MODULE_NAME = 'Mirasvit_Kb';
    const TITLE       = 'Knowledge Base';

    private $objectManager;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    private $scopeConfig;

    private $request;

    public function __construct(
        ObjectManagerInterface $objectManager,
        Context                $context,
        ScopeConfigInterface   $scopeConfig,
        RequestInterface       $request
    ) {
        $this->objectManager = $objectManager;
        $this->eventManager  = $context->getEventDispatcher();
        $this->scopeConfig   = $scopeConfig;
        $this->request       = $request;
    }

    public function isApplicable(): bool
    {
        if ($this->request->getFullActionName() == 'seositemap_index_index') {
            return $this->canShow() && interface_exists('Mirasvit\Kb\Api\Data\SitemapInterface');
        }

        return interface_exists('Mirasvit\Kb\Api\Data\SitemapInterface');
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function initSitemapItem($storeId)
    {
        $result = [];

        $this->eventManager->dispatch('core_register_urlrewrite');

        $kbSitemap = $this->objectManager->get('Mirasvit\Kb\Api\Data\SitemapInterface');

        $result[] = $kbSitemap->getBlogItem($storeId);

        if ($categoryItems = $kbSitemap->getCategoryItems($storeId)) {
            $result[] = $categoryItems;
        }

        if ($postItems = $kbSitemap->getPostItems($storeId)) {
            $result[] = $postItems;
        }

        return $result;
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function getItems($storeId)
    {
        $items = [];
        $sitemapData = $this->initSitemapItem($storeId);
        foreach ($sitemapData as $data) {
            $itemCollection = $data->getCollection();
            foreach ($itemCollection as $item) {
                if (empty($item->getName())) {
                    continue;
                }

                $url = $item->getUrl();
                $baseUrl = $this->objectManager->get('\Magento\Framework\UrlInterface')->getBaseUrl();

                if (strpos($url, $baseUrl) === false) {
                    $url = $baseUrl . $url;
                }

                $items[] = new DataObject([
                    'url'        => $url,
                    'title'      => $item->getName(),
                ]);
            }
        }

        return $items;
    }

    private function canShow(): bool
    {
        return (bool)$this->scopeConfig->getValue('seositemap/frontend/is_show_kb', ScopeInterface::SCOPE_STORE);
    }
}
