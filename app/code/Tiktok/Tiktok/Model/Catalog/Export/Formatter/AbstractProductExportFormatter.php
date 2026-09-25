<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Formatter;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tiktok\Tiktok\Api\Export\ProductDataFormatterInterface;

/**
 * Catalog Product Export Formatter Abstract Class
 */
abstract class AbstractProductExportFormatter implements ProductDataFormatterInterface
{
    /**
     * AbstractProductExportFormatter construct
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Formatter\WeightUnit $weightUnit
     */
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected PriceCurrencyInterface $priceCurrency,
        protected WeightUnit $weightUnit
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getAvailabilityStatus($productData = null): string
    {
        if (empty($productData)) {
            return 'in stock';
        }

        return isset($productData['is_in_stock']) && $productData['is_in_stock'] ? 'in_stock' : 'out_of_stock';
    }

    /**
     * @inheritDoc
     */
    public function getFullProductUrl($productData = null): string
    {
        return !empty($productData) ? $productData : '';
    }

    /**
     * @inheritDoc
     */
    public function formatAdditionalImages($productData = null): array|string
    {
        if (empty($productData) || !isset($productData['images'])) {
            return '';
        }

        return array_map(function ($image) {
            return isset($image['file']) ? $this->getImageUrl($image['file']) : '';
        }, $productData['media_gallery']['images']);
    }

    /**
     * Retrieve image URL
     *
     * @param string $image
     *
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getImageUrl(string $image): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA)
            . 'catalog/product'
            . $image;
    }

    /**
     * @inheritDoc
     */
    public function getFullImageUrl($productData = null): string
    {
        if (empty($productData)) {
            return '';
        }

        return isset($productData) ? $this->getImageUrl($productData) : '';
    }

    /**
     * @inheritDoc
     */
    public function getSalePriceEffectiveDate($startDate = null, $endDate = null): string
    {
        if (empty($startDate) | empty($endDate)) {
            return '';
        }

        return date('Y-m-d\TH:i:s', strtotime($startDate)) . '/' . date('Y-m-d\TH:i:s', strtotime($endDate));
    }

    /**
     * @inheritDoc
     */
    public function getFormattedPrice($productData = null): string
    {
        if (empty($productData)) {
            return '';
        }

        //limit $productData to 2 decimal places
        $price = number_format($productData, 2);

        return ($price) . ' ' . $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    /**
     * @inheritDoc
     */
    public function getFormattedWeight($productData = null): string
    {
        if (empty($productData)) {
            return '';
        }

        $weightUnit = $this->storeManager->getStore()->getConfig('general/locale/weight_unit');
        $weight = number_format((float) $productData, 1) . ' ' . $weightUnit;
        return $this->weightUnit->getFormattedWeightUnit($weight);
    }

    /**
     * @inheritDoc
     */
    public function getFormattedBrand($productData = null): string
    {
        if (empty($productData)) {
            return $this->storeManager->getStore()->getName();
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    public function getFormattedSalePrice($specialPrice = null, $price = null): string
    {
        if ($specialPrice === null && $price === null) {
            return '';
        }

        if (isset($specialPrice)) {
            $specialPrice = number_format((float)$specialPrice, 2);
            return ($specialPrice) . ' ' . $this->storeManager->getStore()->getCurrentCurrencyCode();
        }

        if (!empty($price)) {
            $price = number_format((float)$price, 2);
            return ($price) . ' ' . $this->storeManager->getStore()->getCurrentCurrencyCode();
        }

        return '';
    }

    /**
     * Retrieve product condition
     *
     * @param mixed $productData
     *
     * @return string|null
     */
    public function getCondition(mixed $productData = null)
    {
        if (empty($productData)) {
            return '';
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getFormattedProductGroupId($tiktokProductGroupId = null, $sku = null, $parentData = null)
    {
        if (!empty($tiktokProductGroupId)) {
            return $tiktokProductGroupId;
        }
        if (!empty($parentData) && !empty($parentData['parent_sku'])) {
            return ($parentData['parent_type'] ?? '') == 'configurable'
                ? $parentData['parent_sku'] . '_' . $sku
                : $parentData['parent_sku'];
        }
        return $sku ?: '';
    }
}
