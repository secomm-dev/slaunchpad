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

namespace Mageplaza\ExtraFee\Controller\Product;

use DateTime;
use Exception;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Helper\ValidateHelper;
use Mageplaza\ExtraFee\Model\ResourceModel\Rule\CollectionFactory;
use Mageplaza\ExtraFee\Model\Rule;
use Mageplaza\ExtraFee\Model\Total\Quote\ExtraFee as ExtraFeeCalculate;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Controller\Product
 */
class ExtraFee extends Action
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var CollectionFactory
     */
    protected $ruleCollection;

    /**
     * @var ExtraFeeCalculate
     */
    protected $extraFeeCalculate;

    /**
     * @var PricingHelper
     */
    protected $pricingHelper;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var ValidateHelper
     */
    protected $validateHelper;

    /**
     * ExtraFee constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param Cart $cart
     * @param ProductRepository $productRepository
     * @param CollectionFactory $ruleCollection
     * @param PricingHelper $pricingHelper
     * @param Data $helperData
     * @param QuoteFactory $quoteFactory
     * @param ProductFactory $productFactory
     * @param ExtraFeeCalculate $extraFeeCalculate
     * @param CustomerSession $customerSession
     * @param ValidateHelper $validateHelper
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        Cart $cart,
        ProductRepository $productRepository,
        CollectionFactory $ruleCollection,
        PricingHelper $pricingHelper,
        Data $helperData,
        QuoteFactory $quoteFactory,
        ProductFactory $productFactory,
        ExtraFeeCalculate $extraFeeCalculate,
        CustomerSession $customerSession,
        ValidateHelper $validateHelper
    ) {
        $this->storeManager      = $storeManager;
        $this->productRepository = $productRepository;
        $this->cart              = $cart;
        $this->ruleCollection    = $ruleCollection;
        $this->extraFeeCalculate = $extraFeeCalculate;
        $this->pricingHelper     = $pricingHelper;
        $this->helperData        = $helperData;
        $this->productFactory    = $productFactory;
        $this->customerSession   = $customerSession;
        $this->validateHelper    = $validateHelper;

        parent::__construct($context);
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * @return ResultInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $result       = $this->resultFactory->create('json');
        $productId    = $this->_request->getParam('product');
        $extraFeeData = [];

        if ($productId) {
            $productId = $this->_request->getParam('product');
            $params    = $this->_request->getParams();
            $storeId   = $this->storeManager->getStore()->getId();
            try {
                $product = $this->productRepository->getById($productId, false, $storeId);
                if ($product->getTypeId() == 'configurable') {
                    if (empty(reset($params['super_attribute']))) {
                        $children = $product->getTypeInstance()->getUsedProducts($product);
                        $child    = $children[0];
                        $options  = [];

                        $productAttributeOptions = $product->getTypeInstance()
                            ->getConfigurableAttributesAsArray($product);
                        foreach ($productAttributeOptions as $option) {
                            $options[$option['attribute_id']] = $child->getData($option['attribute_code']);
                        }
                        $params['super_attribute'] = $options;
                    }
                }
                $extraFeeData = $this->addProduct($product, $params, $extraFeeData);
            } catch (Exception $e) {
                $extraFeeData = [];
            }
        }

        if (count($extraFeeData)) {
            $extraFeeContent = $this->getExtraFeeContent($extraFeeData);
            $result->setData(['content' => $extraFeeContent]);
        } else {
            $extraFeeContent = '<p>' . __('There are no extra fee can be applied') . '</p>';
            $result->setData(['content' => $extraFeeContent]);
        }

        return $result;
    }

    /**
     * @param $product
     * @param $params
     * @param $extraFeeData
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function addProduct($product, $params, $extraFeeData)
    {
        $price     = 0;
        $weight    = 0;
        $groupIds  = [];
        $storeId   = $this->helperData->getStoreId();
        $request   = new DataObject($params);
        $quoteCart = $this->quoteFactory->create();
        $totalQty  = $quoteCart->getItemsQty() ?: 0;
        $quoteCart->addProduct($product, $request);

        // Assign products to quote items for better performance and $product has been add Attribute Data
        $product = $this->validateHelper->assignProducts($quoteCart->getItemsCollection(), $product);

        if (array_key_exists('super_group', $params)) {
            foreach ($params['super_group'] as $id => $qty) {
                if ($qty > 0) {
                    $groupIds[] = $id;
                }
            }
        }

        foreach ($quoteCart->getAllItems() as $item) {
            if (count($groupIds) && in_array($item->getProductId(), $groupIds)) {
                $price    += $item->getPrice() * $item->getQty();
                $weight   += $item->getWeight() * $item->getQty();
                $totalQty += $item->getQty();
            } else {
                if ($item->getProductId() == $product->getId()) {
                    $price    = $item->getPrice() * $item->getQty();
                    $weight   = $item->getWeight() * $item->getQty();
                    $totalQty = $item->getQty();
                    break;
                }
            }
        }

        $quoteCart->setItemsQty($totalQty);
        $quoteCart->setWeight($weight);
        $quoteCart->setBaseSubtotal($price);
        $quoteCart->setBaseSubtotalWithDiscount($price);

        $customerId = $this->customerSession->isLoggedIn() ? $this->customerSession->getCustomerId() : null;
        if ($customerId) {
            $quoteCart->setCustomerId($customerId);
        }

        $ruleCollection = $this->helperData->getRuleCollection();
        /** @var Rule $rule */
        foreach ($ruleCollection as $rule) {
            if (!$this->helperData->checkCustomerGroup($rule) || !$this->helperData->checkStoreIds($rule)) {
                continue;
            }
            if ($rule->validateProduct($quoteCart)) {
                if ($rule->getApplyFor() == 2 && !$rule->validateProductActions($product)) {
                    continue;
                }
                $ruleData  = $this->getRuleData($rule, $quoteCart);
                $labels    = $rule->getlabels() ? Data::jsonDecode($rule->getlabels()) : [];
                $ruleLabel = isset($labels[$storeId]) ? ($labels[$storeId] ?: $rule->getName()) : '';

                $ruleId                               = $rule->getId();
                $extraFeeData[$ruleId]['name']        = $ruleLabel;
                $extraFeeData[$ruleId]['description'] = $rule->getDescription();

                $data = array_key_exists('data', $extraFeeData[$ruleId]) ? $extraFeeData[$ruleId]['data'] : [];
                if (!array_key_exists($ruleLabel, $data)) {
                    $data[$ruleLabel] = [];
                }
                foreach ($ruleData as $key => $value) {
                    $data[$ruleLabel] = $value;
                }
                $extraFeeData[$ruleId]['data'] = $data;
            }
        }

        return $extraFeeData;
    }

    /**
     * @param $rule
     * @param $quote
     *
     * @return array|string
     * @throws NoSuchEntityException
     */
    protected function getRuleData($rule, $quote)
    {
        /** @var Rule $rule */
        $ruleName = $rule->getName();
        $taxClass = $rule->getFeeTax();
        $storeId  = $this->storeManager->getStore()->getId();
        $data     = [];

        if ($rule->getApplyType() == 1) {
            $labels          = $rule->getlabels() ? Data::jsonDecode($rule->getlabels()) : [];
            $ruleLabel       = isset($labels[$storeId]) ? ($labels[$storeId] ?: $rule->getName()) : '';
            $calculatedFee   = $this->extraFeeCalculate->calculateExtraFeeAmount($quote, $rule, $taxClass);
            $ruleFeeAmount   = $this->pricingHelper->currencyByStore($calculatedFee[0], $storeId, true, false);
            $data[$ruleName] = $ruleLabel . ': ' . $ruleFeeAmount;
        } else {
            $options = $rule->getOptions() ? Data::jsonDecode($rule->getOptions())['option']['value'] : [];
            foreach ($options as $option) {
                $ruleLabel         = isset($option[$storeId]) ? ($option[$storeId] ?: $option[0]) : '';
                $calculatedFee     = $this->extraFeeCalculate->calculateExtraFeeAmount($quote, $option, $taxClass,
                    $rule);
                $ruleFeeAmount     = $this->pricingHelper->currencyByStore($calculatedFee[0], $storeId, true, false);
                $data[$ruleName][] = $ruleLabel . ': ' . $ruleFeeAmount;
            }
        }

        return $data;
    }

    /**
     * @param $ruleData
     *
     * @return string
     */
    protected function getExtraFeeContent($ruleData)
    {
        $html = '';
        foreach ($ruleData as $key => $data) {
            $html .= '<div class="mp-extrafee-name">' . $data['name'] . '</div>';
            $html .= '<div class="mp-extrafee-desc">' . $data['description'] . '</div>';
            $html .= '<div class="content"><ul>';

            foreach ($data['data'] as $label => $option) {
                if (is_array($option)) {
                    foreach ($option as $opt) {
                        $html .= '<li>' . $opt . '</li>';
                    }
                } else {
                    $html .= '<li>' . $option . '</li>';
                }
            }
            $html .= '</ul></div>';
        }

        return $html;
    }
}
