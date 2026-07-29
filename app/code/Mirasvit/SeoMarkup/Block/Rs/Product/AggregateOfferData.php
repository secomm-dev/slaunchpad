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

namespace Mirasvit\SeoMarkup\Block\Rs\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\Render;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\Locale\FormatInterface;
use Mirasvit\SeoMarkup\Model\Config;
use Mirasvit\SeoMarkup\Model\Config\ProductConfig;

class AggregateOfferData
{
    private $offerData;

    private $productRepository;

    private $productConfig;

    private $layout;

    private $formatInterface;

    public function __construct(
        OfferData         $offerData,
        ProductRepository $productRepository,
        ProductConfig     $productConfig,
        LayoutInterface   $layout,
        FormatInterface   $formatInterface
    ) {
        $this->offerData         = $offerData;
        $this->productRepository = $productRepository;
        $this->productConfig     = $productConfig;
        $this->layout            = $layout;
        $this->formatInterface   = $formatInterface;
    }

    public function getData(ProductInterface $product, StoreInterface $store): array
    {
        $processingProduct = $product;
        $processingStore   = $store;
        $isPriceEnabled    = $this->productConfig->isPriceEnabled((int)$processingStore->getStoreId());

        $values = [
            '@type'  => 'AggregateOffer',
            'offers' => [],
        ];

        if ($isPriceEnabled) {
            $values['priceCurrency'] = $processingStore->getCurrentCurrencyCode();
        }

        $minPrice = 0;
        $maxPrice = 0;

        $type         = $processingProduct->getTypeId();
        $typeInstance = $processingProduct->getTypeInstance();

        switch ($type) {
            case 'configurable':
                $children = $typeInstance->getUsedProducts($processingProduct);

                foreach ($children as $item) {
                    $offer = $this->offerData->getData($item, $processingStore, true);
                    if (!$offer) {
                        continue;
                    }

                    if ($isPriceEnabled && isset($offer['price'])) {
                        $minPrice = $minPrice == 0 ? $offer['price'] : min($minPrice, $offer['price']);
                        $maxPrice = max($maxPrice, $offer['price']);
                    }

                    $values['offers'][] = $offer;
                }

                if (empty($values['offers'])) {
                    if ($isPriceEnabled) {
                        $childrenIds = $typeInstance->getChildrenIds($processingProduct->getId());
                        $allChildIds  = !empty($childrenIds) ? array_values($childrenIds)[0] : [];
                        foreach ($allChildIds as $childId) {
                            try {
                                $child = $this->productRepository->getById((int)$childId);
                                $offer = $this->offerData->getData($child, $processingStore, true);
                                if (isset($offer['price'])) {
                                    $price = (float)$offer['price'];
                                    if ($price > 0) {
                                        $minPrice = $minPrice === 0 ? $price : min($minPrice, $price);
                                        $maxPrice = max($maxPrice, $price);
                                    }
                                }
                            } catch (\Exception $e) {
                                continue;
                            }
                        }
                    }
                    $values['offers'][] = $this->getOutOfStockOffer();
                }

                break;
            case 'grouped':
                $childrenIds = $typeInstance->getChildrenIds($processingProduct->getId());
                foreach (array_values($childrenIds)[0] as $childId) {
                    $offer = $this->offerData->getData($this->productRepository->getById($childId), $processingStore, true);
                    if (!$offer) {
                        continue;
                    }

                    if ($isPriceEnabled && isset($offer['price'])) {
                        $minPrice = $minPrice == 0 ? $offer['price'] : min($minPrice, $offer['price']);
                        $maxPrice = max($maxPrice, $offer['price']);
                    }

                    $values['offers'][] = $offer;
                }

                if (empty($values['offers'])) {
                    $values['offers'][] = $this->getOutOfStockOffer();
                }

                break;
            case 'bundle':
                $offer = $this->offerData->getData($processingProduct, $processingStore, true);

                if (!$offer) {
                    break;
                }

                $values['offers'][] = $offer;
                $includeTax         = $this->offerData->isIncludingTax();
                $priceModel         = $processingProduct->getPriceModel();
                $minPrice           = $priceModel->getTotalPrices($processingProduct, 'min', $includeTax);
                $maxPrice           = $priceModel->getTotalPrices($processingProduct, 'max', $includeTax);

                break;
            default:
                $offer = $this->offerData->getData($processingProduct, $processingStore, true);
                if (!$offer) {
                    break;
                }

                $values['offers'][] = $offer;
                $priceData          = $processingProduct->getPriceInfo()->getPrice('final_price');
                $minPrice           = $priceData->getMinimalPrice()->__toString();
                $maxPrice           = $priceData->getMaximalPrice()->__toString();

                break;
        }

        if (!$minPrice) {
            $minPrice = strip_tags(html_entity_decode($this->getPrice($processingProduct)));
            preg_match_all('/[0-9\.\,]+/', $minPrice, $matches);

            if (isset($matches[0][0])) {
                $minPrice = $matches[0][0];
            }
        }

        $minPrice = $this->formatInterface->getNumber($minPrice);

        if ($isPriceEnabled) {
            $values['lowPrice']  = number_format((float)$minPrice, 2, '.', '');
            $values['highPrice'] = number_format((float)$maxPrice, 2, '.', '');
        }

        $values['offerCount'] = count($values['offers']);

        if (!$minPrice || $values['offerCount'] < 2 || $this->productConfig->isSinglePriceMode()) {
            $offer = $this->offerData->getData($product, $store);
            if ($isPriceEnabled && (float)$minPrice > 0 && (float)($offer['price'] ?? 0) === 0.0) {
                $offer['price'] = number_format((float)$minPrice, 2, '.', '');
            }
            return $offer;
        }

        return $values;
    }

    public function getPrice(ProductInterface $product): string
    {
        $priceRender = $this->layout->getBlock('product.price.render.default');
        if (!$priceRender) {
            $priceRender = $this->layout->createBlock(
                Render::class,
                'product.price.render.default',
                ['data' => ['price_render_handle' => 'catalog_product_prices']]
            );
        }

        $price = '';
        if ($priceRender) {
            /** @var mixed $priceRender */
            $price = $priceRender->render(
                FinalPrice::PRICE_CODE,
                $product,
                [
                    'display_minimal_price'  => true,
                    'use_link_for_as_low_as' => true,
                    'zone'                   => Render::ZONE_ITEM_LIST,
                ]
            );
        }

        return $price;
    }

    private function getOutOfStockOffer(): array
    {
        return [
            '@type'        => 'Offer',
            'availability' => Config::HTTP_SCHEMA_ORG . '/OutOfStock',
        ];
    }
}
