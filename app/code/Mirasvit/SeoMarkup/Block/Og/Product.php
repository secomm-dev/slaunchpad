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

namespace Mirasvit\SeoMarkup\Block\Og;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Directory\Block\Currency;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\Store;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\Seo\Helper\Price as PriceHelper;
use Mirasvit\SeoMarkup\Model\Config\ProductConfig;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Product extends AbstractBlock
{
    private const PRODUCT_IN_STOCK     = 'in stock';
    private const PRODUCT_OUT_OF_STOCK = 'out of stock';
    private const OG_SHORT_DESCRIPTION = 'og:short_description';

    private $imageHelper;

    private $currency;

    private $outputHelper;

    private $stateService;

    private $productConfig;

    private $priceHelper;

    public function __construct(
        ImageHelper           $imageHelper,
        Currency              $currency,
        OutputHelper          $outputHelper,
        StateServiceInterface $stateService,
        ProductConfig         $productConfig,
        PriceHelper           $priceHelper,
        Template\Context      $context
    ) {
        $this->imageHelper       = $imageHelper;
        $this->currency          = $currency;
        $this->outputHelper      = $outputHelper;
        $this->stateService      = $stateService;
        $this->productConfig     = $productConfig;
        $this->priceHelper       = $priceHelper;

        parent::__construct($context);
    }

    protected function getMeta(): ?array
    {
        $product = $this->stateService->getProduct();

        if (!$product) {
            return null;
        }

        /** @var Store $store */
        $store = $this->_storeManager->getStore();

        $finalPrice = $this->priceHelper->getFinalPrice($product, $store);

        $meta = [
            'og:type'        => 'product',
            'og:url'         => $this->_urlBuilder->escape($product->getProductUrl()),
            'og:title'       => $this->pageConfig->getTitle()->get(),
            'og:description' => $this->outputHelper->productAttribute(
                $product,
                $product->getData('short_description'),
                self::OG_SHORT_DESCRIPTION
            ),
            'og:image'       => $this->getImageUrl($product),
            'og:site_name'   => $store->getFrontendName(),
        ];

        $storeId = (int)$store->getId();

        if ($this->productConfig->isPriceEnabled($storeId)) {
            $meta['product:price:amount']   = $finalPrice;
            $meta['product:price:currency'] = $this->currency->getCurrentCurrencyCode();
        }

        if ($this->productConfig->isAvailabilityEnabled($storeId)) {
            /** @var \Magento\Catalog\Model\Product $product */
            $productAvailability = method_exists($product, 'isAvailable')
                ? $product->isAvailable()
                : $product->isInStock();

            $meta['product:availability'] = $productAvailability ? self::PRODUCT_IN_STOCK : self::PRODUCT_OUT_OF_STOCK;
        }

        return $meta;
    }

    /**
     * @param ProductModel $product
     */
    protected function getImageUrl(ProductInterface $product): string
    {
        return $this->imageHelper->init($product, 'product_base_image')
            ->keepAspectRatio(true)
            ->resize(800)
            ->getUrl();
    }
}
