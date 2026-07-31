<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\GoogleTagManagerPlus\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config extends \Magefan\GoogleTagManager\Model\Config
{
    /**
     * Display Product Price For customer groups
     */
    public const XML_PATH_DISPLAY_PRODUCT_PRICE_FOR = 'mfgoogletagmanager/attributes/display_product_price_for';

    /**
     * Google Ads config
     */
    public const XML_PATH_GOOGLE_ADS_TAG_ID = 'mfgoogletagmanager/ads/tag_id';
    public const XML_PATH_GOOGLE_ADS_CONVERSION_ENABLE = 'mfgoogletagmanager/ads/conversion/enable';
    public const XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_ID = 'mfgoogletagmanager/ads/conversion/purchase/conversion_id';
    public const XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_LABEL = 'mfgoogletagmanager/ads/conversion/purchase/conversion_label';
    public const XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_CART_DATA_ENABLE = 'mfgoogletagmanager/ads/conversion/purchase/cart_data/enable';
    public const XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_CART_DATA_MERCHANT_ID = 'mfgoogletagmanager/ads/conversion/purchase/cart_data/merchant_id';
    public const XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_CART_DATA_FEED_COUNTRY = 'mfgoogletagmanager/ads/conversion/purchase/cart_data/feed_country';
    public const XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_CART_DATA_FEED_LANGUAGE = 'mfgoogletagmanager/ads/conversion/purchase/cart_data/feed_language';
    public const XML_PATH_GOOGLE_ADS_REMARKETING_ENABLE = 'mfgoogletagmanager/ads/remarketing/enable';
    public const XML_PATH_GOOGLE_ADS_REMARKETING_ID = 'mfgoogletagmanager/ads/remarketing/conversion_id';
    public const XML_PATH_GOOGLE_ADS_REMARKETING_LABEL = 'mfgoogletagmanager/ads/remarketing/conversion_label';

    /**
     * Events config
     */
    public const XML_PATH_EVENTS_VIEW_ITEM_LIST_MAX_ITEMS = 'mfgoogletagmanager/events/view_item_list/max_items';

    /**
     * Return customer group IDs allowed to see product price in the data layer.
     *
     * @param string|null $storeId
     * @return array
     */
    public function getDisplayProductPriceForGroups(?string $storeId = null): array
    {
        $allowedGroups = (string)$this->getConfig(self::XML_PATH_DISPLAY_PRODUCT_PRICE_FOR, $storeId);

        if ('' === $allowedGroups) {
            return [];
        }

        return explode(',', $allowedGroups);
    }

    /**
     * Retrieve max number of items for view_item_list event
     *
     * @param string|null $storeId
     * @return int
     */
    public function getViewItemListMaxItems(?string $storeId = null): int
    {
        return (int)$this->getConfig(self::XML_PATH_EVENTS_VIEW_ITEM_LIST_MAX_ITEMS, $storeId);
    }

    /**
     * Retrieve Google Ads Tag ID
     *
     * @param string|null $storeId
     * @return string
     */
    public function getGoogleAdsTagId(?string $storeId = null): string
    {
        return trim((string)$this->getConfig(self::XML_PATH_GOOGLE_ADS_TAG_ID, $storeId));
    }

    /**
     * Retrieve true if conversion tracking enabled
     *
     * @param string|null $storeId
     * @return bool
     */
    public function isConversionTrackingEnabled(?string $storeId = null): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_GOOGLE_ADS_CONVERSION_ENABLE, $storeId) &&
            $this->getPurchaseConversionId($storeId) &&
            $this->getPurchaseConversionLabel($storeId);
    }

    /**
     * Retrieve Google Ads conversion ID
     *
     * @param string|null $storeId
     * @return string
     */
    public function getPurchaseConversionId(?string $storeId = null): string
    {
        return trim((string)$this->getConfig(self::XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_ID, $storeId));
    }

    /**
     * Retrieve Google Ads conversion label
     *
     * @param string|null $storeId
     * @return string
     */
    public function getPurchaseConversionLabel(?string $storeId = null): string
    {
        return trim((string)$this->getConfig(self::XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_LABEL, $storeId));
    }

    /**
     * Retrieve true if remarketing tracking enabled
     *
     * @param string|null $storeId
     * @return bool
     */
    public function isRemarketingEnabled(?string $storeId = null): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_GOOGLE_ADS_REMARKETING_ENABLE, $storeId) &&
            $this->getRemarketingId($storeId);
    }

    /**
     * Retrieve Google Ads remarketing ID
     *
     * @param string|null $storeId
     * @return string
     */
    public function getRemarketingId(?string $storeId = null): string
    {
        return trim((string)$this->getConfig(self::XML_PATH_GOOGLE_ADS_REMARKETING_ID, $storeId));
    }

    /**
     * Retrieve Google Ads remarketing Label
     *
     * @param string|null $storeId
     * @return string
     */
    public function getRemarketingLabel(?string $storeId = null): string
    {
        return trim((string)$this->getConfig(self::XML_PATH_GOOGLE_ADS_REMARKETING_LABEL, $storeId));
    }

    /**
     * Retrieve true if conversion cart data tracking enabled
     *
     * @param string|null $storeId
     * @return bool
     */
    public function isConversionCartDataEnabled(?string $storeId = null): bool
    {
        return $this->getConfig(self::XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_CART_DATA_ENABLE, $storeId)
            && $this->isConversionTrackingEnabled();
    }

    /**
     * Retrieve Cart Data Merchant ID
     *
     * @param string|null $storeId
     * @return string
     */
    public function getCartDataMerchantId(?string $storeId = null): string
    {
        return trim((string)$this->getConfig(self::XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_CART_DATA_MERCHANT_ID, $storeId));
    }

    /**
     * Retrieve Cart Data Feed Country
     *
     * @param string|null $storeId
     * @return string
     */
    public function getCartDataFeedCountry(?string $storeId = null): string
    {
        return trim((string)$this->getConfig(self::XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_CART_DATA_FEED_COUNTRY, $storeId));
    }

    /**
     * Retrieve Cart Data Feed Language
     *
     * @param string|null $storeId
     * @return string
     */
    public function getCartDataFeedLanguage(?string $storeId = null): string
    {
        return trim((string)$this->getConfig(self::XML_PATH_GOOGLE_ADS_PURCHASE_CONVERSION_CART_DATA_FEED_LANGUAGE, $storeId));
    }
}
