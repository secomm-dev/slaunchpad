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
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sitemap\Helper\Data as DataHelper;
use Magento\Store\Model\ScopeInterface;
use Mirasvit\Seo\Service\Alternate\BlogStrategy;
use Mirasvit\Seo\Service\Config\AlternateConfig;
use Mirasvit\SeoSitemap\Repository\Provider\AbstractProvider;

class BlogProvider extends AbstractProvider
{
    const KEY         = 'blogs';
    const MODULE_NAME = 'Mirasvit_BlogMx';
    const TITLE       = 'Blog';

    private $objectManager;

    private $dataHelper;

    private $blogStrategy;

    private $alternateConfig;

    private $scopeConfig;

    private $request;

    public function __construct(
        ObjectManagerInterface $objectManager,
        DataHelper             $sitemapData,
        BlogStrategy           $blogStrategy,
        AlternateConfig        $alternateConfig,
        ScopeConfigInterface   $scopeConfig,
        RequestInterface       $request
    ) {
        $this->objectManager   = $objectManager;
        $this->dataHelper      = $sitemapData;
        $this->blogStrategy    = $blogStrategy;
        $this->alternateConfig = $alternateConfig;
        $this->scopeConfig     = $scopeConfig;
        $this->request         = $request;
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
     *
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
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     *
     * @param int $storeId
     *
     * @return array
     */
    public function getItems($storeId)
    {
        $postRepository = $this->objectManager->get('Mirasvit\BlogMx\Repository\PostRepository');

        $collection = $postRepository->getCollection();
        $collection->addStoreFilter($storeId)
            ->addVisibilityFilter();
        $urlBuilder = class_exists('Mirasvit\BlogMx\Model\Url\UrlBuilder') ? $this->objectManager->get('Mirasvit\BlogMx\Model\Url\UrlBuilder') : '';

        $items         = [];
        $catIds        = [];
        $defaultRobots = (string)$this->scopeConfig->getValue('blog/seo/default_robots', ScopeInterface::SCOPE_STORE, $storeId);

        if ($urlBuilder) {
            $urlBuilder->setStoreId($storeId);
        }

        foreach ($collection as $key => $post) {
            $post = $postRepository->get((int)$post->getId(), (int)$storeId);

            if (!$post) {
                continue;
            }

            if ($this->isNoindex((string)$post->getRobots(), $defaultRobots)) {
                continue;
            }

            $post->setStoreId($storeId);

            $catIds = array_merge($catIds, $post->getCategoryIds());

            $item = new DataObject([
                'id'         => $post->getId(),
                'url'        => $urlBuilder ? $urlBuilder->getPostUrl($post) : $post->getUrl(),
                'title'      => $post->getName(),
                'updated_at' => $post->getUpdatedAt(),
            ]);

            if ($this->alternateConfig->addHreflangToSitemap((int)$storeId)) {
                $item->setAlternates($this->blogStrategy->getBlogAlternates('post', $post));
            }

            $items[] = $item;
        }

        $catIds = array_unique($catIds);

        if (count($catIds)) {
            $catRepository = $this->objectManager->get('Mirasvit\BlogMx\Repository\CategoryRepository');

            $catCollection = $catRepository->getCollection();
            $catCollection->addStoreFilter($storeId)
                ->addVisibilityFilter()
                ->addFieldToFilter('category_id', ['in' => $catIds]); // use only categories with posts

            foreach ($catCollection as $category) {
                $category = $catRepository->get((int)$category->getId(), (int)$storeId);

                if (!$category) {
                    continue;
                }

                if ($this->isNoindex((string)$category->getRobots(), $defaultRobots)) {
                    continue;
                }

                $category->setStoreId((int)$storeId);

                $item = new DataObject([
                    'id'         => $category->getId(),
                    'url'        => $urlBuilder ? $urlBuilder->getCategoryUrl($category) : $category->getUrl(),
                    'title'      => $category->getName(),
                    'updated_at' => $category->getUpdatedAt(),
                ]);

                if ($this->alternateConfig->addHreflangToSitemap((int)$storeId)) {
                    $item->setAlternates($this->blogStrategy->getBlogAlternates('category', $category));
                }

                $items[] = $item;
            }
        }

        if ($urlBuilder) {
            $tagRepository = $this->objectManager->get('Mirasvit\BlogMx\Repository\TagRepository');
            $tagCollection = $tagRepository->getCollection()->addStoreFilter($storeId);

            foreach ($tagCollection as $tag) {
                $tag = $tagRepository->get((int)$tag->getId(), (int)$storeId);

                if (!$tag || !$tag->getUrlKey()) {
                    continue;
                }

                if ($this->isNoindex((string)$tag->getRobots(), $defaultRobots)) {
                    continue;
                }

                $tagItem = new DataObject([
                    'id'         => $tag->getId(),
                    'url'        => $urlBuilder->getTagUrl($tag),
                    'title'      => $tag->getName(),
                    'updated_at' => $tag->getUpdatedAt(),
                ]);

                if ($this->alternateConfig->addHreflangToSitemap((int)$storeId)) {
                    $tagItem->setAlternates($this->blogStrategy->getBlogAlternates('tag', $tag));
                }

                $items[] = $tagItem;
            }

            $authorRepository = $this->objectManager->get('Mirasvit\BlogMx\Repository\AuthorRepository');
            $authorCollection = $authorRepository->getCollection()
                ->addStoreFilter($storeId)
                ->addFieldToFilter("is_active", 1);

            foreach ($authorCollection as $author) {
                $author = $authorRepository->get((int)$author->getId(), (int)$storeId);

                if (!$author || !$author->getUrlKey()) {
                    continue;
                }

                if ($this->isNoindex((string)$author->getRobots(), $defaultRobots)) {
                    continue;
                }

                $authorItem = new DataObject([
                    'id'         => $author->getId(),
                    'url'        => $urlBuilder->getAuthorUrl($author),
                    'title'      => $author->getName(),
                    'updated_at' => $author->getUpdatedAt(),
                ]);

                if ($this->alternateConfig->addHreflangToSitemap((int)$storeId)) {
                    $authorItem->setAlternates($this->blogStrategy->getBlogAlternates('author', $author));
                }

                $items[] = $authorItem;
            }
        }

        return $items;
    }

    private function isNoindex(string $entityRobots, string $defaultRobots): bool
    {
        $effective = $entityRobots ?: $defaultRobots;

        return $effective !== '' && stripos($effective, 'NOINDEX') !== false;
    }

    private function canShow(): bool
    {
        return (bool)$this->scopeConfig->getValue('seositemap/frontend/is_show_blog', ScopeInterface::SCOPE_STORE);
    }
}
