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



namespace Mirasvit\Seo\Plugin\Adminhtml\Catalog\Model\System\Config\Backend\Catalog\Url\Rewrite\Suffix;

use Exception;
use Magento\Catalog\Model\System\Config\Backend\Catalog\Url\Rewrite\Suffix;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Core\Service\DegradationReporter;
use Mirasvit\Seo\Service\TrailingSlashService;

/**
 * @see \Magento\Catalog\Model\System\Config\Backend\Catalog\Url\Rewrite\Suffix::afterSave()
 * @see \Magento\Catalog\Model\System\Config\Backend\Catalog\Url\Rewrite\Suffix::afterDeleteCommit()
 */
class ApplyTrailingSlashPlugin
{
    private $trailingSlashService;

    private $config;

    private $storeManager;

    private $degradationReporter;

    public function __construct(
        TrailingSlashService  $trailingSlashService,
        ScopeConfigInterface  $config,
        StoreManagerInterface $storeManager,
        DegradationReporter   $degradationReporter
    ) {
        $this->trailingSlashService = $trailingSlashService;
        $this->config               = $config;
        $this->storeManager         = $storeManager;
        $this->degradationReporter  = $degradationReporter;
    }

    public function afterAfterSave(Suffix $subject, Suffix $result): Suffix
    {
        $map = [
            ProductUrlPathGenerator::XML_PATH_PRODUCT_URL_SUFFIX => ProductUrlRewriteGenerator::ENTITY_TYPE,
            CategoryUrlPathGenerator::XML_PATH_CATEGORY_URL_SUFFIX => CategoryUrlRewriteGenerator::ENTITY_TYPE,
        ];
        if (isset($map[$result->getPath()]) && $result->isValueChanged()) {
            try {
                $storeIds   = $this->getStoreIds($result);
                $entityType = $map[$result->getPath()];

                foreach ($storeIds as $storeId) {
                    $this->trailingSlashService->processUrlRewrites((int)$storeId, $entityType);
                }
            } catch (Exception $e) {
                $this->degradationReporter->report('seo', 'trailing_slash.rewrite_regeneration_failed', $e->getMessage());
            }
        }

        return $result;
    }

    public function afterAfterDeleteCommit(Suffix $subject, Suffix $result): Suffix
    {
        $map = [
            ProductUrlPathGenerator::XML_PATH_PRODUCT_URL_SUFFIX => ProductUrlRewriteGenerator::ENTITY_TYPE,
            CategoryUrlPathGenerator::XML_PATH_CATEGORY_URL_SUFFIX => CategoryUrlRewriteGenerator::ENTITY_TYPE,
        ];
        if (isset($map[$result->getPath()]) && $result->isValueChanged()) {
            try {
                $storeIds   = $this->getStoreIds($result);
                $entityType = $map[$result->getPath()];

                foreach ($storeIds as $storeId) {
                    $this->trailingSlashService->processUrlRewrites((int)$storeId, $entityType);
                }
            } catch (Exception $e) {
                $this->degradationReporter->report('seo', 'trailing_slash.rewrite_regeneration_failed', $e->getMessage());
            }
        }

        return $result;
    }

    private function getStoreIds(Suffix $suffix): array
    {
        if ($suffix->getScope() == 'stores') {
            $storeIds = [$suffix->getScopeId()];
        } elseif ($suffix->getScope() == 'websites') {
            $website = $this->storeManager->getWebsite($suffix->getScopeId());
            $storeIds = array_keys($website->getStoreIds());
            $storeIds = array_diff($storeIds, $this->getOverrideStoreIds($suffix, $storeIds));
        } else {
            $storeIds = array_keys($this->storeManager->getStores());
            $storeIds = array_diff($storeIds, $this->getOverrideStoreIds($suffix, $storeIds));
        }
        return $storeIds;
    }

    private function getOverrideStoreIds(Suffix $suffix, array $storeIds): array
    {
        $excludeIds = [];
        foreach ($storeIds as $storeId) {
            $suffixValue = $this->config->getValue($suffix->getPath(), ScopeInterface::SCOPE_STORE, $storeId);
            if ($suffixValue != $suffix->getOldValue()) {
                $excludeIds[] = $storeId;
            }
        }
        return $excludeIds;
    }
}
