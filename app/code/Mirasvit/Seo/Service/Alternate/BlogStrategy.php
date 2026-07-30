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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager;
use Mirasvit\Seo\Api\Service\Alternate\StrategyInterface;
use Mirasvit\Seo\Api\Service\Alternate\UrlInterface;

class BlogStrategy implements StrategyInterface
{
    private $manager;

    private $url;

    private $scopeConfig;

    public function __construct(
        Manager              $manager,
        UrlInterface         $url,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->manager     = $manager;
        $this->url         = $url;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getStoreUrls(): array
    {
        if (!$this->manager->isEnabled('Mirasvit_BlogMx')) {
            return [];
        }

        if ($this->scopeConfig->isSetFlag('blog/seo/alternate_hreflang')) {
            return []; //alternate links are added by blog module
        }

        $storeUrls = $this->url->getStoresCurrentUrl();

        if (!$storeUrls) {
            return [];
        }

        $blogRegistry = null;

        if (class_exists('\Mirasvit\BlogMx\Registry')) {
            $blogRegistry = ObjectManager::getInstance()->get('\Mirasvit\BlogMx\Registry');
        }

        if (!$blogRegistry) {
            return [];
        }

        if ($post = $blogRegistry->getPost()) {
            return $this->getBlogAlternates('post', $post);
        } elseif ($category = $blogRegistry->getCategory()) {
            return $this->getBlogAlternates('category', $category);
        } else {
            return [];
        }
    }

    public function getAlternateUrl(array $storeUrls, ?int $entityId = null, ?int $storeId = null): array
    {
        return [];
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getBlogAlternates(string $type, $entity)
    {
        if (!in_array($type, ['post', 'category', 'tag', 'author'])) {
            return [];
        }

        $urlBuilderFactory = ObjectManager::getInstance()->get('\Mirasvit\BlogMx\Model\Url\UrlBuilderFactory');

        if (!$urlBuilderFactory) {
            return [];
        }

        $storeManager = ObjectManager::getInstance()->get('\Magento\Store\Model\StoreManagerInterface');

        if ($storeManager === null) {
            return [];
        }

        $urls = [];

        foreach ($storeManager->getStores() as $store) {
            if (!in_array(0, $entity->getStoreIds()) && !in_array($store->getId(), $entity->getStoreIds())) {
                continue;
            }

            $classType  = ucfirst($type);
            $repository = ObjectManager::getInstance()->get("\Mirasvit\BlogMx\Repository\\{$classType}Repository");
            if ($entity->getId() && $repository !== null) {
                $fetched = $repository->get((int)$entity->getId(), (int)$store->getId());
                if ($fetched !== null) {
                    $entity = $fetched;
                }
            }
            $urlBuilder = $urlBuilderFactory->create();

            if (method_exists($urlBuilder, 'setStoreId')) {
                $urlBuilder->setStoreId((int)$store->getId());
                $urls[$store->getId()] = $urlBuilder->{"get{$classType}Url"}($entity);
            } elseif (method_exists($urlBuilder, 'getCategoryUrl') && method_exists($urlBuilder, 'getPostUrl')) {
                if ($type === 'category') {
                    $urls[$store->getId()] = $urlBuilder->getCategoryUrl($entity);
                } elseif ($type === 'post') {
                    $urls[$store->getId()] = $urlBuilder->getPostUrl($entity);
                }
            }
        }

        return count($urls) > 1 ? $this->url->processStoreParamForDuplicates($urls) : [];
    }
}
