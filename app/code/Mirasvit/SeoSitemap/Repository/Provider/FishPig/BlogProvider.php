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



namespace Mirasvit\SeoSitemap\Repository\Provider\FishPig;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sitemap\Helper\Data as DataHelper;
use Magento\Store\Model\ScopeInterface;
use Mirasvit\SeoSitemap\Repository\Provider\AbstractProvider;

class BlogProvider extends AbstractProvider
{
    const KEY         = 'blogs';
    const MODULE_NAME = 'FishPig_WordPress';
    const TITLE       = 'Blog';

    private $objectManager;

    private $dataHelper;

    private $scopeConfig;

    private $request;

    public function __construct(
        ObjectManagerInterface $objectManager,
        DataHelper             $sitemapData,
        ScopeConfigInterface   $scopeConfig,
        RequestInterface       $request
    ) {
        $this->objectManager = $objectManager;
        $this->dataHelper    = $sitemapData;
        $this->scopeConfig   = $scopeConfig;
        $this->request       = $request;
    }

    public function isApplicable(): bool
    {
        if ($this->request->getFullActionName() == 'seositemap_index_index') {
            return $this->canShow();
        }

        return true;
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function initSitemapItem($storeId)
    {
        $result = [];

        $result[] = new DataObject([
            'changefreq' => $this->dataHelper->getPageChangefreq($storeId),
            'priority'   => $this->dataHelper->getPagePriority($storeId),
            'collection' => $this->getItems($storeId),
        ]);

        return $result;
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function getItems($storeId)
    {
        $items = [];
        try {
            $emulation = $this->objectManager->create('Magento\Store\Model\App\Emulation');
            $emulation->startEnvironmentEmulation($storeId, 'frontend', true);

            $postTypeRepository = $this->objectManager->get('FishPig\WordPress\Model\PostTypeRepository');
            $collection         = $this->objectManager->get('FishPig\WordPress\Model\ResourceModel\Post\Collection');

            $collection->addIsViewableFilter();

            if (is_object($postTypeRepository) && method_exists($postTypeRepository, 'getPublic')) {
                $postTypes = call_user_func([$postTypeRepository, 'getPublic']);
                if (is_array($postTypes)) {
                    $collection->addPostTypeFilter(array_keys($postTypes));
                }
            }

            $emulation->stopEnvironmentEmulation();

            foreach ($collection as $key => $post) {
                $items[] = new DataObject([
                    'id'         => $post->getId(),
                    'url'        => $post->getUrl(),
                    'title'      => $post->getName(),
                    'updated_at' => $post->getPostModifiedDate(),
                ]);
            }
        } catch (\Exception $e) {
            // intentional no-op: FishPig_WordPress is an optional third-party integration; if it is
            // absent or errors, the sitemap simply omits blog items.
        }

        return $items;
    }

    private function canShow(): bool
    {
        return (bool)$this->scopeConfig->getValue('seositemap/frontend/is_show_fishpig_blog', ScopeInterface::SCOPE_STORE);
    }
}
