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
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\UrlInterfaceFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Api\Service\Alternate\StrategyInterface;
use Mirasvit\Seo\Api\Service\Alternate\UrlInterface;

class AheadworksBlogStrategy implements StrategyInterface
{
    private const BLOG_ENTITY_POST     = 'Post';
    private const BLOG_ENTITY_CATEGORY = 'Category';

    protected $request;

    private $manager;

    private $url;

    private $urlBuilderFactory;

    private $storeManager;

    public function __construct(
        Manager               $manager,
        UrlInterface          $url,
        UrlInterfaceFactory   $urlBuilderFactory,
        StoreManagerInterface $storeManager,
        RequestInterface      $request
    ) {
        $this->manager           = $manager;
        $this->url               = $url;
        $this->urlBuilderFactory = $urlBuilderFactory;
        $this->storeManager      = $storeManager;
        $this->request           = $request;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getStoreUrls(): array
    {
        if (!$this->manager->isEnabled('Aheadworks_Blog')) {
            return [];
        }

        $entityType = null;
        $entityId   = null;

        if ($id = $this->request->getParam('post_id')) {
            $entityId   = $id;
            $entityType = self::BLOG_ENTITY_POST;
        } elseif ($id = $this->request->getParam('blog_category_id')) {
            $entityId   = $id;
            $entityType = self::BLOG_ENTITY_CATEGORY;
        }

        if (!$entityId && !$entityType) {
            return [];
        }

        $repository = ObjectManager::getInstance()->get("\Aheadworks\Blog\Api\\" . $entityType . "RepositoryInterface");

        if (!$repository) {
            return [];
        }

        $entity = $repository->get($entityId);

        if (!$entity) {
            return [];
        }

        return $this->getBlogAlternates(strtolower($entityType), $entity);
    }

    public function getAlternateUrl(array $storeUrls, ?int $entityId = null, ?int $storeId = null): array
    {
        return [];
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getBlogAlternates(string $type, $entity = null)
    {
        if (!in_array($type, ['home', 'post', 'category'])) {
            return [];
        }

        if ($type !== 'home' && !$entity) {
            return [];
        }

        $urls      = [];
        $urlHelper = ObjectManager::getInstance()->create('Aheadworks\Blog\Model\Url');

        if ($type == 'home') {
            $configProvider = ObjectManager::getInstance()->create('Aheadworks\Blog\Model\Config');

            foreach ($this->storeManager->getStores() as $store) {
                $urlBuilder            = $this->urlBuilderFactory->create()->setScope($store);
                $urls[$store->getId()] = $urlBuilder->getUrl($configProvider->getRouteToBlog((int)$store->getId()));
            }

            return count($urls) > 1 ? $this->url->processStoreParamForDuplicates($urls) : null;
        }

        $classType    = ucfirst($type);
        $entityStores = $entity->getStoreIds();

        foreach ($this->storeManager->getStores() as $store) {
            if (!in_array($store->getId(), $entityStores)) {
                continue;
            }

            $urlBuilder = $this->urlBuilderFactory->create()->setScope($store);

            $urls[$store->getId()] = $urlBuilder->getUrl($urlHelper->{"get{$classType}Route"}($entity));
        }

        return count($urls) > 1 ? $this->url->processStoreParamForDuplicates($urls) : [];
    }
}
