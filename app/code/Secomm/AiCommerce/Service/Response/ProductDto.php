<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Response;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product\Image\UrlBuilder as ImageUrlBuilder;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\ConfigurableProduct\Api\Data\OptionInterface;
use Magento\ConfigurableProduct\Api\OptionRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Secomm\AiCommerce\Service\Pricing\PublicPrice;
use Secomm\AiCommerce\Service\Url\PublicUrlResolver;

/**
 * Product DTO — fixed field allowlist. Never emits qty, cost, supplier,
 * tier prices, admin-only attributes or PII.
 */
class ProductDto
{
    private const IMAGE_ROLE = 'category_page_grid';

    /**
     * @param PublicPrice $publicPrice deterministic public price
     * @param PublicUrlResolver $urlResolver store-scoped public/canonical URLs
     * @param ImageUrlBuilder $imageUrlBuilder product image url builder
     * @param OptionRepositoryInterface $optionRepository configurable options API
     * @param ProductAttributeRepositoryInterface $attributeRepository eav attribute registry
     * @param CategoryCollectionFactory $categoryCollectionFactory category collection factory
     */
    public function __construct(
        private readonly PublicPrice $publicPrice,
        private readonly PublicUrlResolver $urlResolver,
        private readonly ImageUrlBuilder $imageUrlBuilder,
        private readonly OptionRepositoryInterface $optionRepository,
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly CategoryCollectionFactory $categoryCollectionFactory
    ) {
    }

    /**
     * Full product DTO (detail endpoint).
     *
     * @param ProductInterface $product product entity
     * @param StoreInterface $store store view scope
     * @param string $availabilityStatus in_stock|out_of_stock
     * @return mixed[] DTO array
     */
    public function toArray(ProductInterface $product, StoreInterface $store, string $availabilityStatus): array
    {
        $urls = $this->urlResolver->getProductUrls($product, $store);

        $data = [
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'product_type' => (string) $product->getTypeId(),
            'public_url' => $urls['public_url'],
            'canonical_url' => $urls['canonical_url'],
            'price' => $this->publicPrice->resolve($store, $product),
            'availability' => ['status' => $availabilityStatus],
        ];

        $shortDescription = trim((string) $product->getShortDescription());

        if ($shortDescription !== '') {
            $data['short_description'] = $shortDescription;
        }

        $image = $this->resolveImage($product);

        if ($image !== null) {
            $data['image'] = $image;
        }

        $categories = $this->resolveCategories($product, $store);

        if ($categories !== []) {
            $data['categories'] = $categories;
        }

        if ($product->getTypeId() === 'configurable') {
            $data['configurable_options'] = $this->resolveConfigurableOptions($product);
        }

        return $data;
    }

    /**
     * Summary subset for search result items.
     *
     * @param ProductInterface $product product entity
     * @param StoreInterface $store store view scope
     * @param string $availabilityStatus in_stock|out_of_stock
     * @return mixed[] DTO array
     */
    public function toSummaryArray(ProductInterface $product, StoreInterface $store, string $availabilityStatus): array
    {
        $urls = $this->urlResolver->getProductUrls($product, $store);

        return [
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'product_type' => (string) $product->getTypeId(),
            'public_url' => $urls['public_url'],
            'canonical_url' => $urls['canonical_url'],
            'price' => $this->publicPrice->resolve($store, $product),
            'availability' => ['status' => $availabilityStatus],
        ];
    }

    /**
     * Resolve the public product image URL.
     *
     * @param ProductInterface $product product entity
     * @return string|null image URL or null when no image exists
     */
    private function resolveImage(ProductInterface $product): ?string
    {
        $file = $product->getData('small_image');

        if ($file === null || $file === '' || $file === 'no_selection') {
            return null;
        }

        return $this->imageUrlBuilder->getUrl($file, self::IMAGE_ROLE);
    }

    /**
     * Resolve the store-visible category references of the product.
     *
     * ONE bounded category collection load — no per-category loads (N+1 rule).
     *
     * @param ProductInterface $product product entity
     * @param StoreInterface $store store view scope
     * @return array[] category references
     */
    private function resolveCategories(ProductInterface $product, StoreInterface $store): array
    {
        $categoryIds = array_values(array_filter(
            array_map('intval', (array) $product->getCategoryIds()),
            static fn (int $id): bool => $id > 2
        ));

        if ($categoryIds === []) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId((int) $store->getId());
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('entity_id', ['in' => $categoryIds]);
        $collection->addAttributeToFilter('is_active', 1);

        $result = [];

        foreach ($collection as $category) {
            $result[] = ['id' => (int) $category->getId(), 'name' => (string) $category->getName()];
        }

        return $result;
    }

    /**
     * Resolve public storefront-facing configurable options (store labels).
     *
     * @param ProductInterface $product configurable product
     * @return array option arrays
     */
    private function resolveConfigurableOptions(ProductInterface $product): array
    {
        try {
            $options = $this->optionRepository->getList((string) $product->getSku());
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return [];
        }

        $result = [];

        foreach ($options as $option) {
            if (!$option instanceof OptionInterface) {
                continue;
            }

            $attributeCode = '';

            try {
                $attributeCode = (string) $this->attributeRepository->getById(
                    (string) $option->getAttributeId()
                )->getAttributeCode();
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                continue;
            }

            $result[] = [
                'attribute_code' => $attributeCode,
                'label' => (string) $option->getLabel(),
                'values' => array_map(
                    static fn ($value): array => ['value' => (string) $value->getValueIndex()],
                    (array) $option->getValues()
                ),
            ];
        }

        return $result;
    }
}
