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



namespace Mirasvit\Seo\Observer\AutoRedirect;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mirasvit\Seo\Model\Config;
use Mirasvit\Seo\Service\AutoRedirect\RemovedProductUrlStorage;
use Mirasvit\Seo\Service\AutoRedirect\RuleManager;
use Mirasvit\Seo\Service\AutoRedirect\TargetResolver;

/**
 * After a product is deleted, build the auto-redirect rule(s) from the request path(s) captured by
 * the delete-before observer (AC 2). The target is resolved from the categories the product was
 * assigned to at delete time.
 */
class ProductDeleteAfterObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var RemovedProductUrlStorage
     */
    private $storage;

    /**
     * @var TargetResolver
     */
    private $targetResolver;

    /**
     * @var RuleManager
     */
    private $ruleManager;

    public function __construct(
        Config                   $config,
        RemovedProductUrlStorage $storage,
        TargetResolver           $targetResolver,
        RuleManager              $ruleManager
    ) {
        $this->config         = $config;
        $this->storage        = $storage;
        $this->targetResolver = $targetResolver;
        $this->ruleManager    = $ruleManager;
    }

    public function execute(Observer $observer): void
    {
        /** @var ProductInterface $product */
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        $productId   = (int)$product->getId();
        $categoryIds = (array)$product->getData('mst_seo_deleted_category_ids');

        // Clear any prior auto-rules for this product before rebuilding from the captured URLs.
        $this->ruleManager->removeForProduct($productId);

        foreach ($this->storage->getStoreIds($productId) as $storeId) {
            if (!$this->config->isAutoRedirectOnDeleteEnabled($storeId)) {
                continue;
            }

            $requestPaths = $this->storage->getRequestPaths($productId, $storeId);
            if (!$requestPaths) {
                continue;
            }

            $target = $this->targetResolver->resolve($categoryIds, $storeId);
            $this->ruleManager->generate($productId, $storeId, $requestPaths, $target);
        }

        $this->storage->clear($productId);
    }
}
