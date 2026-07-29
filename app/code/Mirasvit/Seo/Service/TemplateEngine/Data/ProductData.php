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

namespace Mirasvit\Seo\Service\TemplateEngine\Data;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Helper\Data as SeoHelper;
use Mirasvit\Seo\Helper\Price as PriceHelper;

class ProductData extends AbstractData
{
    const FORMAT_FALSE = false;
    const FORMAT_TRUE = true;
    const INCLUDE_CONTAINER_FALSE = false;
    const INCLUDE_CONTAINER_TRUE = true;

    /**
     * @var \Magento\Catalog\Api\Data\CategoryInterface|null
     */
    private $category;

    /**
     * @var \Magento\Catalog\Api\Data\ProductInterface|null
     */
    private $product;

    private $categoryFactory;

    private $pricingHelper;

    private $registry;

    private $storeManager;

    private $configurableProductResource;

    private $productRepository;

    private $attributeRepository;

    private $priceHelper;

    private $seoHelper;

    public function __construct(
        Registry                            $registry,
        CategoryFactory                     $categoryFactory,
        StoreManagerInterface               $storeManager,
        PricingHelper                       $pricingHelper,
        Configurable                        $configurableProductResource,
        ProductRepositoryInterface          $productRepository,
        ProductAttributeRepositoryInterface $attributeRepository,
        PriceHelper                         $priceHelper,
        SeoHelper                           $seoHelper
    ) {
        $this->registry                    = $registry;
        $this->categoryFactory             = $categoryFactory;
        $this->storeManager                = $storeManager;
        $this->pricingHelper               = $pricingHelper;
        $this->configurableProductResource = $configurableProductResource;
        $this->productRepository           = $productRepository;
        $this->attributeRepository         = $attributeRepository;
        $this->priceHelper                 = $priceHelper;
        $this->seoHelper                   = $seoHelper;

        parent::__construct();
    }

    public function getTitle(): string
    {
        return (string)__('Product Data');
    }

    public function getVariables(): array
    {
        return [
            'name',
            'url',
            'page_title',
            'parent_name',
            'parent_url',
            'category_name',
        ];
    }

    /**
     * Used in GraphQl
     */
    public function setProduct(ProductInterface $product): AbstractData
    {
        $this->product = $product;

        return $this;
    }

    public function getProduct(): ?ProductInterface
    {
        if (!$this->product) {
            return $this->registry->registry('current_product') ?: null;
        }

        return $this->product;
    }

    /**
     * Used in GraphQl
     */
    public function setCategory(CategoryInterface $category): AbstractData
    {
        $this->category = $category;

        return $this;
    }

    public function getCategory(): ?CategoryInterface
    {
        if (!$this->category) {
            return $this->registry->registry('current_category') ?: null;
        }

        return $this->category;
    }

    public function getValue(string $attribute, array $additionalData = []): ?string
    {
        if (empty($attribute)) {
            return null;
        }

        /** @var \Magento\Catalog\Model\Product $product */
        $product = isset($additionalData['product'])
            ? $additionalData['product']
            : $this->getProduct();

        $storeId = isset($additionalData['store'])
            ? $additionalData['store']->getId()
            : $this->storeManager->getStore()->getId();

        if (!$product || !$product->getId()) {
            return null;
        }

        switch ($attribute) {
            case 'price':
            case 'finalprice':
            case 'final_price':
                if ($product->getTypeId() === 'bundle'
                    && $this->hasBundleRequiredOptionWithoutSalableSelection($product)
                ) {
                    return '';
                }

                $price = $this->priceHelper->getFinalPrice($product, $this->storeManager->getStore());
                if ((float)$price === 0.0) {
                    return '';
                }

                return (string)$this->pricingHelper->currency($price, self::FORMAT_TRUE, self::INCLUDE_CONTAINER_FALSE);

            case 'url':
                return (string)$product->getProductUrl();

            case 'name':
                if (!$product->getData('name')) {
                    // Use getAttributeRawValue instead of load() to avoid triggering
                    // Gallery ReadHandler and potential infinite recursion
                    $name = $product->getResource()->getAttributeRawValue(
                        $product->getId(),
                        'name',
                        $storeId
                    );

                    return $name ? (string)$name : null;
                }

                return (string)$product->getData('name');

            case 'sku':
                return (string)$product->getSku();

            case 'category_name':
                if ($category = $this->getCategory()) {
                    return (string)$category->getName();
                }

                if ($categoryId = $this->seoHelper->getPreviousCategoryId()) {
                    $categoryIds = [$categoryId];
                } else {
                    $categoryIds = $product->getCategoryIds();
                    $categoryIds = array_reverse($categoryIds);
                }

                if (isset($categoryIds[0])) {
                    return (string)$this->categoryFactory->create()
                        ->setStoreId($storeId)
                        ->load($categoryIds[0])
                        ->getName();
                }

                return null;

            case 'page_title':
                return (string)$product->getMetaTitle();
        }

        if (strpos($attribute, 'parent_') === 0) {
            $parentProductIds = $this->configurableProductResource->getParentIdsByChild($product->getId());

            $newAttribute  = substr($attribute, 7);
            $parentProduct = null;

            if (isset($parentProductIds[0])) {
                $parentProduct = $this->productRepository->getById($parentProductIds[0], false, $storeId);
            }

            return $parentProduct && ($value = $this->getValue($newAttribute, ['product' => $parentProduct]))
                ? $value
                : $this->getValue($newAttribute, ['product' => $product]);
        }

        try {
            $attr = $this->attributeRepository->get($attribute);

            if (isset($additionalData['store'])) {
                // required for use correct attribute labels (color Black FR) during url-generation
                $attr->setStoreId($storeId);
            }

            $value = $attr->getFrontend()->getValue($product);

            if (empty($value)) {
                $value = $product->getResource()->getAttributeRawValue($product->getId(), $attribute, $storeId);

                if (is_array($value)) {
                    if (!empty($value)) {
                        $value = array_values($value)[0];
                    } else {
                        $value = null;
                    }
                }

                if ($value && in_array($attr->getFrontendInput(), ['select', 'multiselect'])) {
                    foreach ($attr->getFrontend()->getSelectOptions() as $option) {
                        if ($option['value'] == $value) {
                            $value = $option['label'];
                        }
                    }
                }
            }

            if (is_array($value)) {
                if (!empty($value)) {
                    $value = array_values($value)[0];
                } else {
                    $value = null;
                }
            }

            return $value ? (string)$value : null;
        } catch (NoSuchEntityException $e) {
            // intentional no-op: placeholder is not a catalog attribute ⇒ fall back to the
            // product's own data method below.
        }

        $value = $product->getDataUsingMethod($attribute);

        if ($value) {
            $value = strpos($attribute, 'price') !== false
                ? (string)$this->pricingHelper->currency($value, self::FORMAT_TRUE, self::INCLUDE_CONTAINER_FALSE)
                : (string)$value;

            return $value;
        }

        return null;
    }

    /**
     * Magento's bundle min-price calculation skips OOS selections, so when a required option
     * has no salable selection left, the resulting "from"/min price is misleading. Detect that
     * shape so the price resolver can return an empty string and let the bracket-conditional
     * [...] template suppress the price fragment in the meta tag.
     */
    private function hasBundleRequiredOptionWithoutSalableSelection(ProductInterface $product): bool
    {
        $typeInstance = $product->getTypeInstance();

        if (!$typeInstance instanceof \Magento\Bundle\Model\Product\Type) {
            return false;
        }

        if (!$product instanceof \Magento\Catalog\Model\Product) {
            return false;
        }

        foreach ($typeInstance->getOptionsCollection($product) as $option) {
            if (!$option->getRequired()) {
                continue;
            }

            $selections = $typeInstance->getSelectionsCollection([$option->getId()], $product);
            $hasSalable = false;
            foreach ($selections as $selection) {
                if ($selection->isSalable()) {
                    $hasSalable = true;
                    break;
                }
            }

            if (!$hasSalable) {
                return true;
            }
        }

        return false;
    }
}
