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


namespace Mirasvit\SeoSitemap\Repository\Provider\Amasty;


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
    const MODULE_NAME = 'Amasty_Blog';
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

    public function getItems($storeId)
    {
        $items = [];

        $categoryRepository = $this->objectManager->get('Amasty\Blog\Model\Repository\CategoriesRepository');

        if ($categoryRepository) {
            foreach ($categoryRepository->getActiveCategories((int)$storeId) as $category) {
                $items[] = new DataObject([
                    'id'         => $category->getCategoryId(),
                    'url'        => $category->getUrl(),
                    'title'      => $category->getName(),
                    'updated_at' => $category->getUpdatedAt(),
                ]);
            }
        }

        $postRepository = $this->objectManager->get('Amasty\Blog\Model\Repository\PostRepository');

        if ($postRepository) {
            foreach ($postRepository->getActivePosts((int)$storeId) as $post) {
                $items[] = new DataObject([
                    'id'         => $post->getPostId(),
                    'url'        => $post->getUrl(),
                    'title'      => $post->getTitle(),
                    'updated_at' => $post->getUpdatedAt(),
                ]);
            }
        }

        return $items;
    }

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

    private function canShow(): bool
    {
        return (bool)$this->scopeConfig->getValue('seositemap/frontend/is_show_amasty_blog', ScopeInterface::SCOPE_STORE);
    }
}
