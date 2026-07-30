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

use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Api\Data\StoreInterface;
use Mirasvit\Seo\Api\Config\AlternateConfigInterface as AlternateConfig;
use Mirasvit\Seo\Api\Service\Alternate\UrlInterface;
use Mirasvit\Seo\Helper\Data;

class Url implements UrlInterface
{
    protected $context;

    protected $alternateConfig;

    protected $seoData;

    protected $storeManager;

    protected $stores = [];

    protected $storeBaseUrlsCountValues = [];

    public function __construct(
        Context         $context,
        AlternateConfig $alternateConfig,
        Data            $seoData
    ) {
        $this->context         = $context;
        $this->alternateConfig = $alternateConfig;
        $this->seoData         = $seoData;
        $this->storeManager    = $this->context->getStoreManager();
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getStoresCurrentUrl(): array
    {
        $alternateManualConfig = false;
        $currentStoreGroup = $this->storeManager->getStore()->getGroupId();
        $currentStore = $this->storeManager->getStore();
        $alternateAddMethod = $this->alternateConfig->getAlternateHreflang((int)$currentStore->getId());
        if ($alternateAddMethod == AlternateConfig::ALTERNATE_CONFIGURABLE) {
            $alternateManualConfig = $this->alternateConfig->getAlternateManualConfig((int)$currentStore->getId());
        }
        $storesNumberInGroup = 0;
        $storeUrls = [];
        $storeBaseUrls = [];

        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()
                && ((!$alternateManualConfig
                    && $store->getGroupId() == $currentStoreGroup
                    && $this->alternateConfig->getAlternateHreflang((int)$store->getId()))
                || ($alternateManualConfig
                    && in_array($store->getId(), $alternateManualConfig)))
            ) {
                //we works only with stores which have the same store group
                $this->stores[$store->getId()] = $store;
                $currentUrl = $store->getCurrentUrl(false);
                $storeBaseUrls[$store->getId()] = $store->getBaseUrl();
                $storeUrls[$store->getId()] = new \Magento\Framework\DataObject(
                    [
                        'store_base_url' => $store->getBaseUrl(),
                        'current_url' => $currentUrl,
                        'store_code' => $store->getCode()
                    ]
                );

                ++$storesNumberInGroup;
            }
        }

        $this->storeBaseUrlsCountValues = array_count_values($storeBaseUrls);

        if (count($storeUrls) > 1) {
            foreach ($storeUrls as $storeId => $storeData) {
                $isSimilarLinks = $this->storeBaseUrlsCountValues[$storeData->getStoreBaseUrl()] > 1;

                $storeUrls[$storeId] = $this->_storeUrlPrepare(
                    $storeBaseUrls,
                    $storeData->getStoreBaseUrl(),
                    $storeData->getCurrentUrl(),
                    $storeData->getStoreCode(),
                    $isSimilarLinks,
                    $alternateAddMethod
                );
            }
        }

        if ($storesNumberInGroup > 1 && count($storeUrls) > 1) { //if a current store is multilanguage
            return $storeUrls;
        }

        return [];
    }

    public function getStores(): array
    {
        return $this->stores;
    }

    public function getStoresByStoreId(int $storeId): array
    {
        $stores                = [];
        $alternateManualConfig = false;
        $currentStore          = $this->storeManager->getStore($storeId);
        $currentStoreGroup     = $currentStore->getGroupId();
        $alternateAddMethod    = $this->alternateConfig->getAlternateHreflang($storeId);

        if ($alternateAddMethod == AlternateConfig::ALTERNATE_CONFIGURABLE) {
            $alternateManualConfig = $this->alternateConfig->getAlternateManualConfig((int)$currentStore->getId());
        }

        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()
                && ((!$alternateManualConfig
                        && $store->getGroupId() == $currentStoreGroup
                        && $this->alternateConfig->getAlternateHreflang((int)$store->getId()))
                    || ($alternateManualConfig
                        && in_array($store->getId(), $alternateManualConfig)))
            ) {
                //we works only with stores which have the same store group
                $stores[$store->getId()] = $store;
            }
        }

        return $stores;
    }

    public function getUrlAddition(StoreInterface $store): string
    {
        $urlAddition = (isset($this->storeBaseUrlsCountValues[$store->getBaseUrl()])
            && $this->storeBaseUrlsCountValues[$store->getBaseUrl()] > 1) ?
            (strstr(htmlspecialchars_decode($store->getCurrentUrl(false)), '?') ?: '') : '';

        return $urlAddition;
    }

    public function processStoreParamForDuplicates(array $storeUrls): array
    {
        if (count($storeUrls) <= 1) {
            return $storeUrls;
        }

        $cleanedUrls = [];
        foreach ($storeUrls as $storeId => $url) {
            if (!isset($this->stores[$storeId])) {
                continue;
            }
            $storeCode = $this->stores[$storeId]->getCode();
            $cleanedUrls[$storeId] = $this->removeStoreParamFromUrl($url, $storeCode);
        }

        $urlOccurrences = array_count_values($cleanedUrls);

        foreach ($storeUrls as $storeId => $url) {
            if (!isset($this->stores[$storeId]) || !isset($cleanedUrls[$storeId])) {
                continue;
            }

            $storeCode = $this->stores[$storeId]->getCode();
            $cleanedUrl = $cleanedUrls[$storeId];
            $hasDuplicate = $urlOccurrences[$cleanedUrl] > 1;

            if ($hasDuplicate) {
                if (strpos($url, '___store=') === false) {
                    $separator = (strpos($url, '?') === false) ? '?' : '&';
                    $storeUrls[$storeId] = $url . $separator . '___store=' . $storeCode;
                }
            } else {
                $storeUrls[$storeId] = $cleanedUrl;
            }
        }

        return $storeUrls;
    }

    private function removeStoreParamFromUrl(string $url, string $storeCode): string
    {
        $url = str_replace('&amp;', '&', $url);

        if (strpos($url, '___store=' . $storeCode) === false) {
            return $url;
        }

        if (strpos($url, '?___store=' . $storeCode) !== false
            && strpos($url, '&') === false) {
            return str_replace('?___store=' . $storeCode, '', $url);
        }

        if (strpos($url, '?___store=' . $storeCode . '&') !== false) {
            return str_replace('?___store=' . $storeCode . '&', '?', $url);
        }

        if (strpos($url, '&___store=' . $storeCode) !== false) {
            return str_replace('&___store=' . $storeCode, '', $url);
        }

        return $url;
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _storeUrlPrepare(
        array $storeBaseUrls,
        string $storeBaseUrl,
        string $currentUrl,
        string $storeCode,
        bool $isSimilarLinks,
        int $alternateAddMethod
    ): string {
        if (strpos($currentUrl, $storeBaseUrl) === false && !$alternateAddMethod) {
            $currentUrl = str_replace($storeBaseUrls, $storeBaseUrl, $currentUrl); // fix bug with incorrect base urls
        }

        $currentUrl = str_replace('&amp;', '&', $currentUrl);
        $currentUrl = preg_replace('/SID=(.*?)(&|$)/', '', $currentUrl) ?? $currentUrl;

        //cut get params for AMASTY_XLANDING if "Cut category additional data for alternate url" enabled
        if ($this->alternateConfig->isHreflangCutCategoryAdditionalData()
            && $this->seoData->getFullActionCode() == AlternateConfig::AMASTY_XLANDING) {
            $currentUrl = strtok($currentUrl, '?') ?: $currentUrl;
        }

        $deleteStoreQuery = (substr_count($storeBaseUrl, '/') > 3) ? true : false;

        if (strpos($currentUrl, '___store=' . $storeCode) === false
            || (!$deleteStoreQuery && $isSimilarLinks)) {
            return $currentUrl;
        }

        if (strpos($currentUrl, '?___store=' . $storeCode) !== false
            && strpos($currentUrl, '&') === false) {
            $currentUrl = str_replace('?___store=' . $storeCode, '', $currentUrl);
        } elseif (strpos($currentUrl, '?___store=' . $storeCode) !== false
            && strpos($currentUrl, '&') !== false) {
            $currentUrl = str_replace('?___store=' . $storeCode . '&', '?', $currentUrl);
        } elseif (strpos($currentUrl, '&___store=' . $storeCode) !== false) {
            $currentUrl = str_replace('&___store=' . $storeCode, '', $currentUrl);
        }

        return $currentUrl;
    }
}
