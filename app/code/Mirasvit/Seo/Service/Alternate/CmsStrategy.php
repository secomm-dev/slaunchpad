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

use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as CmsPageCollectionFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Api\Config\AlternateConfigInterface as AlternateConfig;
use Mirasvit\Seo\Api\Service\Alternate\StrategyInterface;
use Mirasvit\Seo\Api\Service\Alternate\UrlInterface;
use Mirasvit\Seo\Helper\Version as VersionHelper;

class CmsStrategy implements StrategyInterface
{
    protected $url;

    protected $page;

    protected $pageCollectionFactory;

    protected $request;

    protected $version;

    protected $resource;

    protected $alternateConfig;

    protected $storeManager;

    protected $pageRepository;

    public function __construct(
        UrlInterface             $url,
        CmsPage                  $page,
        CmsPageCollectionFactory $pageCollectionFactory,
        HttpRequest              $request,
        VersionHelper            $version,
        ResourceConnection       $resource,
        AlternateConfig          $alternateConfig,
        StoreManagerInterface    $storeManager,
        PageRepositoryInterface  $pageRepository
    ) {
        $this->url                   = $url;
        $this->page                  = $page;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->request               = $request;
        $this->version               = $version;
        $this->resource              = $resource;
        $this->alternateConfig       = $alternateConfig;
        $this->storeManager          = $storeManager;
        $this->pageRepository        = $pageRepository;
    }

    public function getStoreUrls(): array
    {
        $storeUrls = $this->url->getStoresCurrentUrl();

        return $this->getAlternateUrl($storeUrls);
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getAlternateUrl(array $storeUrls, ?int $entityId = null, ?int $storeId = null): array
    {
        if ($entityId) {
            $cmsPageId    = $entityId;
            $cmsStoresIds = $this->pageRepository->getById($cmsPageId)->getStoreId();
        } else {
            $cmsPageId    = $this->page->getPageId();
            $cmsStoresIds = $this->page->getStoreId();
        }

        if (!is_array($cmsStoresIds)) {
            $cmsStoresIds = [$cmsStoresIds];
        }

        if ($storeId) {
            $allowedStores = $this->url->getStoresByStoreId($storeId);
        } else {
            $storeId       = (int)$this->storeManager->getStore()->getStoreId();
            $allowedStores = $this->url->getStores();
        }

        $alternateGroupInstalled = false;

        //check if alternate_group exist in cms_page table
        if (($pageObject = $this->pageCollectionFactory->create()->getItemById($cmsPageId))
            && is_object($pageObject) && ($pageObjectData = $pageObject->getData())
            && array_key_exists('alternate_group', $pageObjectData)) {
            $alternateGroupInstalled = true;
        }

        if (!$alternateGroupInstalled) {
            return $storeUrls;
        }

        $cmsPage = $this->pageCollectionFactory->create()
            ->addFieldToSelect('alternate_group')
            ->addFieldToSelect('identifier')
            ->addFieldToFilter('page_id', ['eq' => $cmsPageId])
            ->getFirstItem();

        $alternateGroup = $cmsPage->getAlternateGroup();

        if (empty($storeUrls)) {
            foreach ($this->storeManager->getStores() as $store) {
                if (isset($allowedStores[$store->getId()])) {
                    $storeUrls[$store->getId()] = $store->getBaseUrl() . $cmsPage->getIdentifier();
                }
            }
        }

        if ($cmsStoresIds[0] == 0 //use if alternate groups configured and use alternate configuration
            && $alternateGroup
            && $storeId
            && $this->alternateConfig->getAlternateHreflang($storeId) == AlternateConfig::ALTERNATE_CONFIGURABLE
            && ($stores = $this->alternateConfig->getAlternateManualConfig($storeId, false))) {
            $cmsPages = $this->getCmsPages($alternateGroup);

            if (count($cmsPages) > 0) {
                $storeUrls     = []; // use only links with alternate_group
                $pageStoreData = [];

                foreach ($cmsPages as $page) {
                    $pageStoreData[$page['store_id']] = $page;
                }

                foreach ($stores as $store) {
                    if (isset($allowedStores[$store])) {
                        $page              = $pageStoreData[$store] ?? $pageStoreData[0];
                        $pageIdentifier    = $page['identifier'];
                        $fullAction        = $this->request->getFullActionName();
                        $baseStoreUrl      = $allowedStores[$store]->getBaseUrl();
                        $storeUrls[$store] = ($fullAction == 'cms_index_index')
                            ? $baseStoreUrl
                            : $baseStoreUrl . $pageIdentifier;
                    }
                }
            }
        } elseif ($alternateGroup && $cmsStoresIds[0] != 0) {
            $cmsPages = $this->getCmsPages($alternateGroup);

            if (count($cmsPages) > 0) {
                $storeUrls = []; // use only links with alternate_group
                foreach ($cmsPages as $page) {
                    if (isset($allowedStores[$page['store_id']])) {
                        $pageIdentifier = $page['identifier'];
                        $fullAction = $this->request->getFullActionName();
                        $baseStoreUrl = $allowedStores[$page['store_id']]->getBaseUrl();
                        $storeUrls[$page['store_id']] = ($fullAction == 'cms_index_index') ? $baseStoreUrl
                            : $baseStoreUrl . $pageIdentifier;
                    }
                }
            }
        } elseif (!$alternateGroup && $cmsStoresIds[0] != 0) {
            foreach ($storeUrls as $storeId => $url) {
                if (!in_array($storeId, $cmsStoresIds)) {
                    unset($storeUrls[$storeId]); // remove links to non-exist pages
                }
            }

            if (count($storeUrls) == 1) {
                $storeUrls = []; // page doesn't have variations
            }
        }

        return $this->url->processStoreParamForDuplicates($storeUrls);
    }

    protected function getCmsPages(string $alternateGroup): array
    {
        $cmsCollection = $this->pageCollectionFactory->create()
            ->addFieldToSelect(['alternate_group', 'identifier'])
            ->addFieldToFilter('alternate_group', ['eq' => $alternateGroup])
            ->addFieldToFilter('is_active', true);
        $table = $this->resource->getTableName('cms_page_store');
        $storeTablePageId = ($this->version->isEe() || $this->version->isB2b()) ? 'row_id' : 'page_id';
        $cmsCollection->getSelect()
            ->join(
                [
                    'storeTable' => $table],
                'main_table.' . $storeTablePageId . ' = storeTable.' . $storeTablePageId,
                ['store_id' => 'storeTable.store_id']
            );

        return $cmsCollection->getData();
    }
}
