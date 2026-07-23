<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Block\Email;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Directory\Model\PriceCurrency;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template as AbstractTemplate;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\Store;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Config;
use Mageplaza\AbandonedCart\Helper\Data as ModuleHelper;
use Mageplaza\AbandonedCart\Model\System\Config\Source\RelatedProductOptions;

/**
 * Class Template
 * @package Mageplaza\AbandonedCart\Block\Email
 */
class Template extends AbstractTemplate
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

    /**
     * @var StockItemRepository
     */
    protected $stockItemRepository;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var PriceCurrency
     */
    protected $priceCurrency;

    /**
     * @var ModuleHelper
     */
    protected $helperData;

    /**
     * @var Data
     */
    protected $taxHelper;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var AbstractProduct
     */
    protected $abstractProduct;

    /**
     * @var UrlInterface
     */
    protected $frontUrlModel;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * Template constructor.
     *
     * @param Context $context
     * @param ProductRepositoryInterface $productRepository
     * @pramm StockItemRepository $stockItemRepository
     * @param PriceCurrency $priceCurrency
     * @param ModuleHelper $helperData
     * @param QuoteFactory $quoteFactory
     * @param AbstractProduct $abstractProduct
     * @param UrlInterface $frontUrlModel
     * @param EncryptorInterface $encryptor
     * @param array $data
     */
    public function __construct(
        Context                    $context,
        ProductRepositoryInterface $productRepository,
        StockItemRepository        $stockItemRepository,
        PriceCurrency              $priceCurrency,
        ModuleHelper               $helperData,
        QuoteFactory               $quoteFactory,
        AbstractProduct            $abstractProduct,
        UrlInterface               $frontUrlModel,
        EncryptorInterface         $encryptor,
        array                      $data = []
    ) {
        $this->_productRepository   = $productRepository;
        $this->stockItemRepository  = $stockItemRepository;
        $this->imageHelper          = $context->getImageHelper();
        $this->priceCurrency        = $priceCurrency;
        $this->helperData           = $helperData;
        $this->taxHelper            = $context->getTaxData();
        $this->quoteFactory         = $quoteFactory;
        $this->abstractProduct      = $abstractProduct;
        $this->frontUrlModel        = $frontUrlModel;
        $this->encryptor            = $encryptor;

        parent::__construct($context, $data);
    }

    /**
     * @return Quote|null
     */
    public function getQuote()
    {
        if ($quoteId = $this->getQuoteId()) {
            return $this->quoteFactory->create()->load($quoteId);
        }

        return null;
    }

    /**
     * Get items in quote
     *
     * @return Item[]
     */
    public function getProductCollection()
    {
        $items = [];

        if ($quote = $this->getQuote()) {
            return $quote->getAllVisibleItems();
        }

        return $items;
    }

    /**
     * Get subtotal in quote
     *
     * @param bool $inclTax
     *
     * @return float|string
     */
    public function getSubtotal($inclTax = false)
    {
        $subtotal = 0;
        if ($quote = $this->getQuote()) {
            $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
            $subtotal = $inclTax ? $address->getSubtotalInclTax() : $address->getSubtotal();
        }

        return $this->priceCurrency->format(
            $subtotal,
            true,
            PriceCurrency::DEFAULT_PRECISION,
            $quote ? $quote->getStoreId() : null,
            $quote ? $quote->getQuoteCurrencyCode() : null
        );
    }

    /**
     * Get image url in quote
     *
     * @param Item $item
     *
     * @return string
     */
    public function getProductImage($item)
    {
        $productId = $item->getProductId();
        try {
            /** @var Product $product */
            $product = $this->_productRepository->getById($productId);
            if($product->getImage())
            {
                /** @var Store $store */
                $store = $this->_storeManager->getStore();
                $imageUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
            }else {
                $imageUrl = $this->imageHelper->getDefaultPlaceholderUrl('small_image');
            }
            return str_replace('\\', '/', $imageUrl);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Get item price in quote
     *
     * @param Item $item
     * @param bool $inclTax
     *
     * @return float|string
     */
    public function getProductPrice($item, $inclTax = false)
    {
        $productPrice = $inclTax ? $item->getRowTotalInclTax() : $item->getRowTotal();
        $quote = $this->getQuote();

        return $this->priceCurrency->format(
            $productPrice,
            false,
            PriceCurrency::DEFAULT_PRECISION,
            $quote ? $quote->getStoreId() : null,
            $quote ? $quote->getQuoteCurrencyCode() : null
        );
    }

    /**
     * @return string
     */
    public function getPlaceholderImage()
    {
        return $this->imageHelper->getDefaultPlaceholderUrl('image');
    }

    /**
     * @return Config
     */
    public function getTaxConfig()
    {
        return $this->taxHelper->getConfig();
    }

    /**
     * @return int|null
     */
    public function getStoreId()
    {
        if ($quote = $this->getQuote()) {
            return $quote->getStoreId();
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->helperData->getConfigGeneral('related_product_title', $this->getStoreId());
    }

    /**
     * @return mixed
     */
    public function getLimitNumberProduct()
    {
        return $this->helperData->getConfigGeneral('related_product_limit', $this->getStoreId());
    }

    /**
     * @return mixed
     */
    public function getIsExcludeOutOfStock()
    {
        return $this->helperData->getConfigGeneral('related_product_out_of_stock', $this->getStoreId());
    }

    /**
     * @return array
     */
    public function getRelatedProductCollection()
    {
        $items = [];

        if ($quote = $this->getQuote()) {
            $relatedProduct = $this->helperData->getConfigGeneral('related_product', $this->getStoreId());

            /** @var Item $quoteItem */
            foreach ($quote->getAllVisibleItems() as $quoteItem) {
                switch ((int)$relatedProduct) {
                    case RelatedProductOptions::RELATED_PRODUCTS:
                        if ($this->getIsExcludeOutOfStock()) {
                            $dataProduct = [];
                            foreach ($quoteItem->getProduct()->getRelatedProducts() as $productItems) {
                                $productStock = $this->stockItemRepository->get($productItems->getId());
                                if ($productStock->getIsInStock()) {
                                    $dataProduct[] = $productItems;
                                }
                            }
                            $items[$quoteItem->getId()] = $dataProduct;
                        } else {
                            $items[$quoteItem->getId()] = $quoteItem->getProduct()->getRelatedProducts();
                        }
                        break;
                    case RelatedProductOptions::UP_SELL_PRODUCTS:
                        if ($this->getIsExcludeOutOfStock()) {
                            $dataProduct = [];
                            foreach ($quoteItem->getProduct()->getUpSellProducts() as $productItems) {
                                $productStock = $this->stockItemRepository->get($productItems->getId());
                                if ($productStock->getIsInStock()) {
                                    $dataProduct[] = $productItems;
                                }
                            }
                            $items[$quoteItem->getId()] = $dataProduct;
                        } else {
                            $items[$quoteItem->getId()] = $quoteItem->getProduct()->getUpSellProducts();
                        }
                        break;
                    case RelatedProductOptions::CROSS_SELL_PRODUCTS:
                        if ($this->getIsExcludeOutOfStock()) {
                            $dataProduct = [];
                            foreach ($quoteItem->getProduct()->getCrossSellProducts() as $productItems) {
                                $productStock = $this->stockItemRepository->get($productItems->getId());
                                if ($productStock->getIsInStock()) {
                                    $dataProduct[] = $productItems;
                                }
                            }
                            $items[$quoteItem->getId()] = $dataProduct;
                        } else {
                            $items[$quoteItem->getId()] = $quoteItem->getProduct()->getCrossSellProducts();
                        }
                        break;
                }
            }
        }

        return array_filter($items);
    }

    /**
     * @param Product $product
     *
     * @return mixed
     */
    public function getProduct($product)
    {
        return $product->load($product->getId());
    }

    /**
     * @param Product $product
     *
     * @return string
     */
    public function getProductUrl($product)
    {
        return $this->abstractProduct->getProductUrl($product);
    }

    /**
     * @param Product $product
     *
     * @return string|string[]|null
     */
    public function getImage($product)
    {
        try {
            if($product->getImage())
            {
                /** @var Store $store */
                $store = $this->_storeManager->getStore();
                $imageUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
            }else {
                $imageUrl = $this->imageHelper->getDefaultPlaceholderUrl('small_image');
            }
            return str_replace('\\', '/', $imageUrl);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getUnsubscribeUrl()
    {
        $url = $this->frontUrlModel->getUrl(
            'abandonedcart/subscriber/unsubscribe',
            [
                'website_id' => $this->_storeManager->getStore($this->getStoreId())->getWebsiteId()
            ]
        );
        return $url.'?email='.($this->getQuote()
                ? $this->encryptor->encrypt((string)$this->getQuote()->getCustomerEmail()) : "");
    }

    /**
     * @return mixed
     */
    public function isEnableUnsubscribeLink()
    {
        return $this->helperData->isEnableUnsubscribeLink($this->getStoreId());
    }
}
