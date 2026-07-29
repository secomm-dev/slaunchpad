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

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Module\Manager;
use Mirasvit\Seo\Api\Service\Alternate\StrategyInterface;
use Mirasvit\Seo\Api\Service\Alternate\UrlInterface;

class MageplazaBlogStrategy implements StrategyInterface
{
    private $manager;

    private $url;

    protected $request;

    private $helperBlog;

    public function __construct(
        Manager          $manager,
        UrlInterface     $url,
        RequestInterface $request
    ) {
        $this->manager = $manager;
        $this->url     = $url;
        $this->request = $request;
    }

    public function getStoreUrls(): array
    {
        if ($this->manager->isEnabled('Mageplaza_Blog') && class_exists('\Mageplaza\Blog\Helper\Data')) {
            $this->helperBlog = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Mageplaza\Blog\Helper\Data::class
            );
            if ($this->helperBlog === null) {
                return [];
            }
            $id = $this->getRequest()->getParam('id');
            $post = $this->helperBlog->getFactoryByType(\Mageplaza\Blog\Helper\Data::TYPE_POST)->create()->load($id);
            $storeUrls = $this->url->getStoresCurrentUrl();
            $allowedStores = explode(',', (string)$post->getStoreIds());

            if (!$storeUrls) {
                return [];
            }

            foreach ($storeUrls as $key => $value) {
                if (!in_array($key, $allowedStores)) {
                    unset($storeUrls[$key]);
                }
            }

            return $this->url->processStoreParamForDuplicates($storeUrls);
        } else {
            return [];
        }
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getAlternateUrl(array $storeUrls, ?int $entityId = null, ?int $storeId = null): array
    {
        return [];
    }
}
