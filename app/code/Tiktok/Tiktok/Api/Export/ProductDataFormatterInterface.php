<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Api\Export;

interface ProductDataFormatterInterface
{
    /**
     * Retrieve product data availability status
     *
     * @param mixed $productData
     * @return mixed
     */
    public function getAvailabilityStatus(mixed $productData = null);

    /**
     * Retrieve product data full URL
     *
     * @param mixed $productData
     * @return mixed
     */
    public function getFullProductUrl(mixed $productData = null);

    /**
     * Format product data additional images
     *
     * @param mixed $productData
     * @return mixed
     */
    public function formatAdditionalImages(mixed $productData = null);

    /**
     * Retrieve product full image URL
     *
     * @param mixed $productData
     *
     * @return mixed
     */
    public function getFullImageUrl(mixed $productData = null);

    /**
     * Retrieve sales price effective date
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @return mixed
     */
    public function getSalePriceEffectiveDate(mixed $startDate = null, mixed $endDate = null);

    /**
     * Retrieve product formatted price
     *
     * @param mixed $productData
     *
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getFormattedPrice(mixed $productData = null);

    /**
     * Retrieve product formatted weight
     *
     * @param mixed $productData
     *
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getFormattedWeight(mixed $productData = null);

    /**
     * Retrieve product formatted brand
     *
     * @param mixed $productData
     *
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getFormattedBrand(mixed $productData = null);

    /**
     * Retrieve product formatted sales price
     *
     * @param mixed $specialPrice
     * @param mixed $price
     *
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getFormattedSalePrice(mixed $specialPrice = null, mixed $price = null);

    /**
     * Retrieve 'Product Group Id' attribute for Tiktok
     *
     * @param string|null $tiktokProductGroupId
     * @param string|null $sku
     * @param array|null $parentData
     *
     * @return ?string
     */
    public function getFormattedProductGroupId(
        ?string $tiktokProductGroupId = null,
        ?string $sku = null,
        ?array $parentData = null
    );
}
