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

namespace Mirasvit\SeoMarkup\Service;

use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\Store;
use Mirasvit\Seo\Api\Service\TemplateEngineServiceInterface;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoContent\Service\ContentService;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\SeoMarkup\Block\Rs\Product\AggregateOfferData;
use Mirasvit\SeoMarkup\Block\Rs\Product\OfferData;
use Mirasvit\SeoMarkup\Block\Rs\Product\RatingData;
use Mirasvit\SeoMarkup\Block\Rs\Product\ReviewData;
use Mirasvit\SeoMarkup\Model\Config;
use Mirasvit\SeoMarkup\Model\Config\AeoConfig;
use Mirasvit\SeoMarkup\Model\Config\ProductConfig;
use Mirasvit\SeoMarkup\Repository\ExtenderRepository;
use Mirasvit\SeoMarkup\Service\HtmlCleanerService;

class ProductRichSnippetsService extends Template
{
    private const SCHEMA_PROPERTY_COLOR    = 'color';
    private const SCHEMA_PROPERTY_SIZE     = 'size';
    private const SCHEMA_PROPERTY_MATERIAL = 'material';
    private const SCHEMA_PROPERTY_PATTERN  = 'pattern';

    private const SCHEMA_TYPE_PRODUCT       = 'Product';
    private const SCHEMA_TYPE_PRODUCT_GROUP = 'ProductGroup';

    private $productConfig;

    private $aeoConfig;

    private $templateEngineService;

    private $offerData;

    private $aggregateOfferData;

    private $reviewData;

    private $ratingData;

    private $imageHelper;

    private $contentService;

    private $extenderRepository;

    private $snippetService;

    private $htmlCleaner;

    private $storeId;

    public function __construct(
        ProductConfig                  $productConfig,
        AeoConfig                      $aeoConfig,
        TemplateEngineServiceInterface $templateEngineService,
        OfferData                      $offerData,
        AggregateOfferData             $aggregateOfferData,
        ReviewData                     $reviewData,
        RatingData                     $ratingData,
        ImageHelper                    $imageHelper,
        ContentService                 $contentService,
        ExtenderRepository             $extenderRepository,
        SnippetService                 $snippetService,
        Context                        $context,
        HtmlCleanerService             $htmlCleaner
    ) {
        $this->productConfig         = $productConfig;
        $this->aeoConfig             = $aeoConfig;
        $this->templateEngineService = $templateEngineService;
        $this->offerData             = $offerData;
        $this->aggregateOfferData    = $aggregateOfferData;
        $this->reviewData            = $reviewData;
        $this->ratingData            = $ratingData;
        $this->imageHelper           = $imageHelper;
        $this->contentService        = $contentService;
        $this->extenderRepository    = $extenderRepository;
        $this->snippetService        = $snippetService;
        $this->htmlCleaner           = $htmlCleaner;

        parent::__construct($context);
    }

    public function getJsonData(?ProductInterface $product, bool $dry = false): ?array
    {
        if (!$product) {
            return null;
        }

        $product = $dry ? $product : $product->load($product->getId());

        /** @var Store $store */
        $store = $this->_storeManager->getStore();

        $extendedTypes = [Configurable::TYPE_CODE, BundleType::TYPE_CODE, Grouped::TYPE_CODE];
        if ($dry === false && in_array($product->getTypeId(), $extendedTypes)) {
            $offer = $this->aggregateOfferData->getData($product, $store);
        } else {
            $offer = $this->offerData->getData($product, $store, $dry);
        }

        $isProductGroup = $this->productConfig->isProductVariantsEnabled($this->getStoreId())
            && ($product->getTypeId() === Configurable::TYPE_CODE);

        $variants = $isProductGroup ? $this->getVariants($product) : [];
        if ($isProductGroup && empty($variants)) {
            $isProductGroup = false;
        }

        $sku = $this->templateEngineService->render('[product_sku]', ['product' => $product]);

        $values = [
            '@context'        => Config::HTTP_SCHEMA_ORG,
            '@type'           => $isProductGroup ? self::SCHEMA_TYPE_PRODUCT_GROUP : self::SCHEMA_TYPE_PRODUCT,
            'name'            => $this->getName($product),
            'sku'             => $sku,
            'mpn'             => $this->getManufacturerPartNumber($product),
            'image'           => $this->getImage($product),
            'category'        => $this->getCategoryName($product),
            'brand'           => $this->getBrand($product),
            'model'           => $this->getModel($product),
            'color'           => $this->getColor($product),
            'size'            => $this->getSize($product),
            'material'        => $this->getMaterial($product),
            'pattern'         => $this->getPattern($product),
            'weight'          => $this->getWeight($product),
            'width'           => $this->getDimensionValue('width', $product),
            'height'          => $this->getDimensionValue('height', $product),
            'depth'           => $this->getDimensionValue('depth', $product),
            'description'     => $this->getDescription($product),
            'gtin8'           => $this->getGtinValue(8, $product),
            'gtin12'          => $this->getGtinValue(12, $product),
            'gtin13'          => $this->getGtinValue(13, $product),
            'gtin14'          => $this->getGtinValue(14, $product),
            'offers'          => $offer,
            'review'          => $this->reviewData->getData($product, $store),
            'aggregateRating' => $this->ratingData->getData($product, $store),
        ];

        if ($isProductGroup) {
            $values['productGroupID'] = $sku;
            $values['variesBy']       = $this->getVariesBy($product);
            $values['hasVariant']     = $variants;
        }

        $extenders = $this->extenderRepository
            ->getListForProduct($product, ExtenderInterface::PRODUCT_TYPE, $this->getStoreId());
        foreach ($extenders as $extender) {
            $snippetArray = $this->snippetService->renderSnippetVariables($extender->getSnippetArray());
            $values       = $this->snippetService->extendRichSnippet($values, $snippetArray, $extender->getOverride());
        }

        if (!$dry && $this->aeoConfig->isAeoEnabled($this->getStoreId())) {
            $values = $this->applyAeoGraph($values, (string)$product->getProductUrl(), $store);
        }

        return array_filter($values);
    }

    /**
     * AEO: give the Product node a stable @id and cross-reference it into the page graph,
     * so an answer engine can tie this Product to its brand and to the selling Organization.
     */
    private function applyAeoGraph(array $values, string $productUrl, Store $store): array
    {
        $baseUrl = (string)$store->getBaseUrl();

        $orgId = $baseUrl . AeoConfig::ID_ORGANIZATION;

        // The Product node gets a stable identity; the selling Organization is referenced from the
        // Offer (schema.org's `seller` lives on Offer, not Product), so an answer engine can tie the
        // product, its offer and the store together into one connected entity.
        $values['@id'] = $productUrl . AeoConfig::ID_PRODUCT;

        if (isset($values['brand']) && is_array($values['brand'])) {
            $values['brand']['@id'] = $baseUrl . '#brand';
        }

        if (isset($values['offers']) && is_array($values['offers'])) {
            $values['offers'] = $this->stitchOffer($values['offers'], $productUrl, $orgId);
        }

        return $values;
    }

    /**
     * Add an @id to the Offer (or every offer in an AggregateOffer) and point its seller at the
     * Organization node. Leaves the offer shape otherwise untouched.
     */
    private function stitchOffer(array $offer, string $productUrl, string $orgId): array
    {
        $type = $offer['@type'] ?? '';

        if ($type === 'AggregateOffer' && isset($offer['offers']) && is_array($offer['offers'])) {
            foreach ($offer['offers'] as $i => $childOffer) {
                if (is_array($childOffer)) {
                    $offer['offers'][$i] = $this->stitchOffer($childOffer, $productUrl, $orgId);
                }
            }

            return $offer;
        }

        $offer['@id']    = $productUrl . AeoConfig::ID_OFFER;
        $offer['seller'] = ['@id' => $orgId];

        return $offer;
    }

    public function getListItemJsonData(?ProductInterface $product, int $position, bool $dry = false): ?array
    {
        if (!$product) {
            return null;
        }

        $product = $dry ? $product : $product->load($product->getId());

        $values = [
            '@type'    => 'ListItem',
            'position' => $position,
            'name'     => $this->templateEngineService->render('[product_name]', ['product' => $product]),
            'url'      => $product->getProductUrl(),
            'image'    => $this->getImage($product),
        ];

        return array_filter($values);
    }

    private function getManufacturerPartNumber(ProductInterface $product): ?string
    {
        $storeId = $this->getStoreId();
        if (
            $this->productConfig->isMpnEnabled($storeId)
            && $attribute = $this->productConfig->getManufacturerPartNumber($storeId)
        ) {
            return $this->templateEngineService->render("[product_$attribute]", ['product' => $product]) ?: null;
        }

        return null;
    }

    private function getImage(ProductInterface $product): ?string
    {
        if ($this->productConfig->isImageEnabled($this->getStoreId())) {
            return $this->imageHelper->init($product, 'product_page_image_large')->getUrl() ?: null;
        }

        return null;
    }

    private function getCategoryName(ProductInterface $product): ?string
    {
        if (!$this->productConfig->isCategoryEnabled($this->getStoreId())) {
            return null;
        }

        return $this->templateEngineService->render('[product_category_name]', ['product' => $product]) ?: null;
    }

    private function getBrand(ProductInterface $product): ?array
    {
        $brand = '';
        foreach ($this->productConfig->getBrandAttributes($this->getStoreId()) as $attribute) {
            if (!$brand) {
                $brand = $this->templateEngineService->render("[product_$attribute]", ['product' => $product]);
            }
        }

        return $brand ? ['@type' => 'Brand', 'name' => $brand] : null;
    }

    private function getModel(ProductInterface $product): ?string
    {
        $model = null;
        foreach ($this->productConfig->getModelAttributes($this->getStoreId()) as $attribute) {
            if (!$model) {
                $model = $this->templateEngineService->render("[product_$attribute]", ['product' => $product]) ?: null;
            }
        }

        return $model;
    }

    private function getColor(ProductInterface $product): ?string
    {
        $color = null;
        foreach ($this->productConfig->getColorAttributes($this->getStoreId()) as $attribute) {
            if (!$color) {
                $color = $this->templateEngineService->render("[product_$attribute]", ['product' => $product]) ?: null;
            }
        }

        return $color;
    }

    private function getSize(ProductInterface $product): ?string
    {
        $size = null;
        foreach ($this->productConfig->getSizeAttributes($this->getStoreId()) as $attribute) {
            if (!$size) {
                $size = $this->templateEngineService->render("[product_$attribute]", ['product' => $product]) ?: null;
            }
        }

        return $size;
    }

    private function getMaterial(ProductInterface $product): ?string
    {
        $material = null;
        foreach ($this->productConfig->getMaterialAttributes($this->getStoreId()) as $attribute) {
            if (!$material) {
                $material = $this->templateEngineService->render("[product_$attribute]", ['product' => $product]) ?: null;
            }
        }

        return $material;
    }

    private function getPattern(ProductInterface $product): ?string
    {
        $pattern = null;
        foreach ($this->productConfig->getPatternAttributes($this->getStoreId()) as $attribute) {
            if (!$pattern) {
                $pattern = $this->templateEngineService->render("[product_$attribute]", ['product' => $product]) ?: null;
            }
        }

        return $pattern;
    }

    private function getWeight(ProductInterface $product): ?array
    {
        $unitCode = $this->productConfig->getWeightUnitType($this->getStoreId());

        if (!$unitCode) {
            return null;
        }

        $value = $this->templateEngineService->render('[product_weight]', ['product' => $product]);

        if (!$value) {
            return null;
        }

        return [
            '@type'    => 'QuantitativeValue',
            'value'    => number_format((float)$value, 4),
            'unitCode' => $unitCode,
        ];
    }

    private function getDescription(ProductInterface $product): ?string
    {
        $content = $this->contentService->getCurrentContent(TemplateInterface::RULE_TYPE_PRODUCT, $product);

        switch ($this->productConfig->getDescriptionType($this->getStoreId())) {
            case ProductConfig::DESCRIPTION_TYPE_DESCRIPTION:
                $description = $content->getData('full_description');
                $value       = !empty($description)
                    ? $description
                    : $this->templateEngineService->render('[product_description]', ['product' => $product]);
                break;
            case ProductConfig::DESCRIPTION_TYPE_META:
                $description = $content->getData('meta_description');
                $value       = !empty($description)
                    ? $description
                    : $this->templateEngineService->render('[page_meta_description]', ['product' => $product]);
                break;
            case ProductConfig::DESCRIPTION_TYPE_SHORT_DESCRIPTION:
                $description = $content->getData('short_description');
                $value       = !empty($description)
                    ? $description
                    : $this->templateEngineService->render('[product_short_description]', ['product' => $product]);
                break;
            default:
                $value = (string)$product->getShortDescription();
                break;
        }

        if ($value) {
            $value = $this->htmlCleaner->clean($value);
        }

        if ($length = $this->productConfig->getDescriptionLength()) {
            $value = $this->truncate($value, $length);
        }

        return $value ?: null;
    }

    private function getDimensionValue(string $type, ProductInterface $product): ?array
    {
        if (!$this->productConfig->isDimensionsEnabled($this->getStoreId())) {
            return null;
        }

        $unitCode = $this->productConfig->getDimensionUnit($this->getStoreId());

        if (!$unitCode) {
            return null;
        }

        switch ($type) {
            case 'width':
                $attribute = $this->productConfig->getDimensionWidthAttribute($this->getStoreId());
                break;
            case 'height':
                $attribute = $this->productConfig->getDimensionHeightAttribute($this->getStoreId());
                break;
            case 'depth':
                $attribute = $this->productConfig->getDimensionDepthAttribute($this->getStoreId());
                break;
            default:
                $attribute = null;
        }

        if (!$attribute) {
            return null;
        }

        $value = $this->templateEngineService->render("[product_$attribute]", ['product' => $product]);

        if (!$value) {
            return null;
        }

        return [
            '@type'    => 'QuantitativeValue',
            'value'    => $value,
            'unitCode' => $unitCode,
        ];
    }

    private function getGtinValue(int $number, ProductInterface $product): ?string
    {
        switch ($number) {
            case 8:
                $attribute = $this->productConfig->getGtin8Attribute($this->getStoreId());
                break;
            case 12:
                $attribute = $this->productConfig->getGtin12Attribute($this->getStoreId());
                break;
            case 13:
                $attribute = $this->productConfig->getGtin13Attribute($this->getStoreId());
                break;
            case 14:
                $attribute = $this->productConfig->getGtin14Attribute($this->getStoreId());
                break;
            default:
                $attribute = null;
        }

        if (!$attribute) {
            return null;
        }

        return $this->templateEngineService->render("[product_$attribute]", ['product' => $product]) ?: null;
    }

    private function getVariesBy(ProductInterface $product): array
    {
        $variesBy = [];
        foreach ($this->getConfigAttributes($product) as $attributeCode => $label) {
            $schemaOrgProperties = $this->getSchemaOrgProperties();
            $variesBy[]          = isset($schemaOrgProperties[$attributeCode])
                ? Config::HTTP_SCHEMA_ORG . '/' . $schemaOrgProperties[$attributeCode]
                : $label;
        }

        return $variesBy;
    }

    private function getVariants(ProductInterface $product): array
    {
        $variants         = [];
        $configAttributes = $this->getConfigAttributes($product);
        $child            = $product->getTypeInstance()->getUsedProducts($product);

        $parentSku = $this->templateEngineService->render('[product_sku]', ['product' => $product]);

        foreach ($child as $item) {
            $jsonData = $this->getJsonData($item, true);
            $jsonData['inProductGroupWithID'] = $parentSku;

            foreach ($configAttributes as $attributeCode => $label) {
                $value = $item->getAttributeText($attributeCode);
                if (!isset($jsonData[$attributeCode]) && $value) {
                    if (!isset($jsonData['additionalProperty'])) {
                        $jsonData['additionalProperty'] = [];
                    }
                    $jsonData['additionalProperty'][] = [
                        '@type' => 'PropertyValue',
                        'name'  => $label,
                        'value' => $value
                    ];
                }
            }

            $variants[] = $jsonData;
        }

        return $variants;
    }

    private function getConfigAttributes(ProductInterface $product): array
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $typeInstance = $product->getTypeInstance();
        if (!$typeInstance instanceof \Magento\ConfigurableProduct\Model\Product\Type\Configurable) {
            return [];
        }
        $options    = $typeInstance->getConfigurableAttributesAsArray($product);
        $attributes = [];
        foreach ($options as $option) {
            $attributeLabel = $product->getResource()->getAttribute($option[AttributeInterface::ATTRIBUTE_CODE])->getFrontend()->getLabel();
            $attributes[$option[AttributeInterface::ATTRIBUTE_CODE]] = $attributeLabel;
        }

        return $attributes;
    }

    private function getSchemaOrgProperties(): array
    {
        $configAttributes = [];
        $colorAttributes    = array_filter($this->productConfig->getColorAttributes($this->getStoreId()));
        $sizeAttributes     = array_filter($this->productConfig->getSizeAttributes($this->getStoreId()));
        $materialAttributes = array_filter($this->productConfig->getMaterialAttributes($this->getStoreId()));
        $patternAttributes  = array_filter($this->productConfig->getPatternAttributes($this->getStoreId()));

        foreach ($colorAttributes as $attribute) {
            $configAttributes[$attribute] = self::SCHEMA_PROPERTY_COLOR;
        }

        foreach ($sizeAttributes as $attribute) {
            $configAttributes[$attribute] = self::SCHEMA_PROPERTY_SIZE;
        }

        foreach ($materialAttributes as $attribute) {
            $configAttributes[$attribute] = self::SCHEMA_PROPERTY_MATERIAL;
        }

        foreach ($patternAttributes as $attribute) {
            $configAttributes[$attribute] = self::SCHEMA_PROPERTY_PATTERN;
        }

        return $configAttributes;
    }

    private function getStoreId(): int
    {
        if (!isset($this->storeId)) {
            try {
                $this->storeId = (int)$this->_storeManager->getStore()->getId();
            } catch (NoSuchEntityException $exception) {
                return Store::DEFAULT_STORE_ID;
            }
        }

        return $this->storeId;
    }

    private function getName(ProductInterface $product): string
    {
        $value = $this->templateEngineService->render('[product_name]', ['product' => $product]);

        if ($length = $this->productConfig->getNameLength()) {
            $value = $this->truncate($value, $length);
        }

        return $value;
    }

    private function truncate(string $string, int $limit): string
    {
        return mb_substr($string, 0, $limit);
    }
}
