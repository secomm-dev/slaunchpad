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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Model\Rule\Condition;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Config\Model\Config\Source\Locale\Currency;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\SessionFactory;
use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\InventorySales\Model\ResourceModel\GetAssignedStockIdForWebsite;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\Quote\Model\QuoteFactory;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class Advanced
 * @package Mageplaza\ExtraFee\Model\Rule\Condition
 */
class Advanced extends AbstractCondition
{
    /**
     * @var Country
     */
    protected $country;

    /**
     * @var Currency
     */
    protected $currency;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var SessionFactory
     */
    protected $customerSession;

    /**
     * @var StockItemRepository
     */
    protected $stockItemRepository;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * Advanced constructor.
     *
     * @param Context $context
     * @param Country $country
     * @param QuoteFactory $quoteFactory
     * @param ProductRepository $productRepository
     * @param SessionFactory $customerSession
     * @param StoreManagerInterface $storeManager
     * @param Currency $currency
     * @param StockItemRepository $stockItemRepository
     * @param Data $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Country $country,
        QuoteFactory $quoteFactory,
        ProductRepository $productRepository,
        SessionFactory $customerSession,
        StoreManagerInterface $storeManager,
        Currency $currency,
        StockItemRepository $stockItemRepository,
        Data $helperData,
        array $data = []
    ) {
        $this->country             = $country;
        $this->currency            = $currency;
        $this->productRepository   = $productRepository;
        $this->quoteFactory        = $quoteFactory;
        $this->storeManager        = $storeManager;
        $this->customerSession     = $customerSession;
        $this->stockItemRepository = $stockItemRepository;
        $this->helperData          = $helperData;

        parent::__construct($context, $data);
    }

    /**
     * @return $this|AbstractCondition
     */
    public function loadAttributeOptions()
    {
        $attributes = [
            'qty_in_stock'          => __('Quantity In Stock'),
            'billing_country'       => __('Billing Address Country'),
            'shipping_address_line' => __('Shipping Address Line'),
            'city'                  => __('City'),
            'store_currency'        => __('Store View currency'),
        ];

        $this->setAttributeOption($attributes);

        return $this;
    }

    /**
     * @return AbstractCondition
     */
    public function getAttributeElement()
    {
        $element = parent::getAttributeElement();
        $element->setShowAsText(true);

        return $element;
    }

    /**
     * @return string
     */
    public function getInputType()
    {
        switch ($this->getAttribute()) {
            case 'qty_in_stock':
                return 'numeric';

            case 'billing_country':
                return 'select';

            case 'store_currency':
                return 'multiselect';

            case 'shipping_address_line':
                return 'contains';
        }

        return 'string';
    }

    /**
     * @return array|string[][]
     */
    public function getDefaultOperatorInputByType()
    {
        $operator             = parent::getDefaultOperatorInputByType();
        $operator['numeric']  = ['>=', '>', '<=', '<'];
        $operator['contains'] = ['{}', '!{}'];
        $operator['string']   = ['==', '!=', '{}', '!{}', '()', '!()'];

        return $operator;
    }

    /**
     * @return string
     */
    public function getValueElementType()
    {
        switch ($this->getAttribute()) {
            case 'billing_country':
                return 'select';

            case 'store_currency':
                return 'multiselect';
        }

        return 'text';
    }

    /**
     * @return array|mixed
     */
    public function getValueSelectOptions()
    {
        if (!$this->hasData('value_select_options')) {
            switch ($this->getAttribute()) {
                case 'billing_country':
                    $options = $this->country->toOptionArray();
                    break;
                case 'store_currency':
                    $options = $this->currency->toOptionArray();
                    break;
                default:
                    $options = [];
            }
            $this->setData('value_select_options', $options);
        }

        return $this->getData('value_select_options');
    }

    /**
     * @param AbstractModel $model
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function validate(AbstractModel $model)
    {
        $quoteId      = $model->getQuoteId();
        $customer     = $this->customerSession->create()->getCustomer();
        $customerData = [];

        if ($customer->getId()) {
            $customerData['billing_country']       = $customer->getDefaultBillingAddress()
                ? $customer->getDefaultBillingAddress()->getCountryId() : null;
            $customerData['city']                  = $customer->getDefaultShippingAddress()
                ? $customer->getDefaultShippingAddress()->getCity() : null;
            $customerData['shipping_address_line'] = $customer->getDefaultShippingAddress()
                ? $customer->getDefaultShippingAddress()->getStreet() : null;
        }

        if ($quoteId && ($model instanceof \Magento\Quote\Model\Quote\Address)) {
            $quote = $model->getQuote();
        } else {
            $quote = $model;
        }

        if ($this->getAttribute() === 'qty_in_stock') {
            $allItems   = $quote->getAllItems();
            $qtyInStock = 0;

            /** @var \Magento\Quote\Model\Quote\Item $item */
            foreach ($allItems as $item) {
                $qtyInStock += $this->getQtySale($item->getProduct());
                if ($qtyInStock >= $this->getValue()) {
                    //dont need plus more
                    break;
                }
            }
            $model->setQtyInStock($qtyInStock);
        }

        if ($this->getAttribute() === 'billing_country') {
            $countryId = $quote->getBillingAddress() ? $quote->getBillingAddress()->getCountryId() : null;
            if (!$countryId && count($customerData)) {
                $countryId = $customerData['billing_country'];
            }
            $model->setBillingCountry($countryId);
        }

        if ($this->getAttribute() === 'city') {
            $city = $quote->getShippingAddress() ? $quote->getShippingAddress()->getCity() : null;
            if (!$city && count($customerData)) {
                $city = $customerData['city'];
            }
            $model->setCity($city);
        }

        if ($this->getAttribute() === 'store_currency') {
            $currentStore = $this->storeManager->getStore();
            $currencyCode = $currentStore->getCurrentCurrency()->getCode();
            $model->setStoreCurrency($currencyCode);
        }

        if ($this->getAttribute() === 'shipping_address_line') {
            $shippingAddress = $quote->getShippingAddress() ? $quote->getShippingAddress()->getStreet() : null;
            if (!$shippingAddress && count($customerData)) {
                $shippingAddress = $customerData['shipping_address_line'];
            }
            $model->setShippingAddressLine($shippingAddress);
        }

        return parent::validate($model);
    }

    /**
     * @param Product|ProductInterface $product
     *
     * @return float|int
     */
    public function getQtySale($product)
    {
        try {
            $stock = $this->stockItemRepository->get($product->getId());
            if ($this->helperData->versionCompare('2.3.0') && $this->helperData->moduleIsEnable('Magento_Inventory')) {
                $totalQty = 0;

                $getSalableQuantityDataBySku = $this->helperData->createObject(
                    GetSalableQuantityDataBySku::class
                );
                $getAssignedStock            = $this->helperData->createObject(
                    GetAssignedStockIdForWebsite::class
                );

                $websiteCode     = $this->storeManager->getWebsite()->getCode();
                $assignedStockId = $getAssignedStock->execute($websiteCode);

                if ($product->getTypeId() === Configurable::TYPE_CODE) {
                    $typeInstance           = $product->getTypeInstance();
                    $childProductCollection = $typeInstance->getUsedProducts($product);
                    foreach ($childProductCollection as $childProduct) {
                        $qty = $getSalableQuantityDataBySku->execute($childProduct->getSku());
                        foreach ($qty as $value) {
                            if ($value['stock_id'] == $assignedStockId) {
                                $totalQty += isset($value['qty']) ? $value['qty'] : 0;
                            }
                        }
                    }
                } else {
                    $qty = $getSalableQuantityDataBySku->execute($product->getSku());
                    foreach ($qty as $value) {
                        if ($value['stock_id'] == $assignedStockId) {
                            $totalQty += isset($value['qty']) ? $value['qty'] : 0;
                        }
                    }
                }

                return $totalQty;
            }

            return $stock->getIsInStock() ? $stock->getQty() : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}
