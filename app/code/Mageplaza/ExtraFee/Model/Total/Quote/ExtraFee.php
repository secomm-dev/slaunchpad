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

namespace Mageplaza\ExtraFee\Model\Total\Quote;

use Exception;
use Magento\Backend\Model\Session\Quote as BackendModelSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Tax\Model\Calculation;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\ApplyType;
use Mageplaza\ExtraFee\Model\Config\Source\CalculateOptions;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;
use Mageplaza\ExtraFee\Model\Config\Source\FeeType;
use Mageplaza\ExtraFee\Model\Config\Source\FeeTypeItem;
use Mageplaza\ExtraFee\Model\Rule;
use Mageplaza\ExtraFee\Model\RuleFactory;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Model\Total\Quote
 */
class ExtraFee extends AbstractTotal
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var Calculation
     */
    protected $calculation;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var RuleFactory
     */
    protected $ruleFactory;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var BackendModelSession
     */
    protected $backendModelSession;

    /**
     * @var array
     */
    private $validatedRulesCache = [];

    /**
     * @var array
     */
    private $customerGroupCache = [];

    /**
     * @var array
     */
    private $calculatedFeesCache = [];

    /**
     * @var array
     */
    private $quoteStateCache = [];

    /**
     * @var string
     */
    private $lastCollectHash = '';

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var array
     */
    private $loadedRulesCache = [];

    /**
     * @var array
     */
    private $quoteItemsCache = [];

    /**
     * @var array
     */
    private $validationResultsCache = [];

    /**
     * @var ShippingAssignmentInterface
     */
    private $shippingAssignment;

    /**
     * @param BackendModelSession $backendModelSession
     * @param Data $helper
     * @param PriceCurrencyInterface $priceCurrency
     * @param Calculation $calculation
     * @param CustomerSession $customerSession
     * @param RequestInterface $request
     * @param RuleFactory $ruleFactory
     * @param SerializerInterface $serializer
     */
    public function __construct(
        BackendModelSession $backendModelSession,
        Data $helper,
        PriceCurrencyInterface $priceCurrency,
        Calculation $calculation,
        CustomerSession $customerSession,
        RequestInterface $request,
        RuleFactory $ruleFactory,
        SerializerInterface $serializer
    ) {
        $this->backendModelSession = $backendModelSession;
        $this->helper              = $helper;
        $this->priceCurrency       = $priceCurrency;
        $this->calculation         = $calculation;
        $this->customerSession     = $customerSession;
        $this->request             = $request;
        $this->ruleFactory         = $ruleFactory;
        $this->serializer          = $serializer;
    }

    /**
     * Get quote state hash for caching
     *
     * @param $quote
     *
     * @return string
     */
    private function getQuoteStateHash($quote)
    {
        if ($quote instanceof \Magento\Quote\Model\Quote\Address) {
            $shippingAmount = $quote->getShippingAmount() ?: 0;
            $paymentMethod  = $quote->getPaymentMethod() ?: '';
            $shippingMethod = $quote->getShippingMethod() ?: '';
        } else {
            $shippingAmount = $quote->getShippingAddress()->getShippingAmount() ?: 0;
            $paymentMethod =  $quote->getPayment()->getMethod() ?: '';
            $shippingMethod = $quote->getShippingAddress()->getShippingMethod() ?: '';
        }
        $keyParts = [
            $quote->getId(),
            number_format((float) ($quote->getSubtotal() ?: 0), 2, '.', ''),
            number_format((float) ($quote->getGrandTotal() ?: 0), 2, '.', ''),
            (int) ($quote->getItemsQty() ?: 0),
            number_format((float) $shippingAmount, 2, '.', ''),
            $paymentMethod,
            $shippingMethod,
            (int) ($quote->getCustomerGroupId() ?: 0),
            $quote->getUpdatedAt() ?: ''
        ];

        return hash('md5', implode('|', $keyParts));
    }

    /**
     * Enhanced calculation caching with rule-specific cache keys
     *
     * @param Quote|Address $quote
     * @param int $ruleId
     * @param array $options
     * @param string $taxClass
     * @param Rule $rule
     *
     * @return array|null
     */
    private function getCachedCalculation($quote, $ruleId, $options, $taxClass, $rule)
    {
        $quoteHash   = $this->getQuoteStateHash($quote);
        $optionsHash = hash('md5', $this->serializer->serialize($options));
        $cacheKey    = "calc_{$ruleId}_{$optionsHash}_{$taxClass}_{$quoteHash}";

        return $this->calculatedFeesCache[$cacheKey] ?? null;
    }

    /**
     * Cache calculation results with enhanced keys
     *
     * @param Quote|Address $quote
     * @param int $ruleId
     * @param array $options
     * @param string $taxClass
     * @param Rule $rule
     * @param array $calculationResult
     */
    private function setCachedCalculation($quote, $ruleId, $options, $taxClass, $rule, $calculationResult)
    {
        $quoteHash   = $this->getQuoteStateHash($quote);
        $optionsHash = hash('md5', $this->serializer->serialize($options));
        $cacheKey    = "calc_{$ruleId}_{$optionsHash}_{$taxClass}_{$quoteHash}";

        $this->calculatedFeesCache[$cacheKey] = $calculationResult;
    }

    /**
     * Clear calculation cache when quote state changes significantly
     *
     * @param Quote $quote
     */
    private function clearCalculationCache($quote = null)
    {
        if ($quote) {
            $quoteId = $quote->getId() ?: 'temp';
            unset($this->quoteItemsCache[$quoteId]);

            foreach ($this->validationResultsCache as $key => $value) {
                if (strpos($key, $quoteId . '_') === 0) {
                    unset($this->validationResultsCache[$key]);
                }
            }
        }

        $this->calculatedFeesCache = [];
        $this->lastCollectHash     = '';
    }

    /**
     * Collect extra fee totals
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     *
     * @return $this
     * @throws Exception
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);
        $fullActionName = $this->request->getFullActionName();

        if (!$this->helper->isEnabled()
            || ($quote->isVirtual() && $this->_getAddress()->getAddressType() === Address::ADDRESS_TYPE_SHIPPING)
            || (!$quote->isVirtual() && $this->_getAddress()->getAddressType() === Address::ADDRESS_TYPE_BILLING)
        ) {
            return $this;
        }

        // Check if we can skip calculation using cache
        $currentHash = $this->getQuoteStateHash($quote);
        if ($this->lastCollectHash === $currentHash && !empty($this->calculatedFeesCache)) {
            // Use cached results
            foreach ($this->calculatedFeesCache as $code => $amounts) {
                if (isset($amounts['amount']) && isset($amounts['base_amount'])) {
                    $this->setCode($code);
                    $this->_addAmount($amounts['amount']);
                    $this->_addBaseAmount($amounts['base_amount']);
                }
            }

            return $this;
        }

        // Clear cache for new calculation
        $this->calculatedFeesCache = [];
        $this->lastCollectHash     = $currentHash;

        if (in_array($fullActionName, [
            'multishipping_checkout_overview',
            'multishipping_checkout_overviewPost'
        ], true)) {
            /**
             * Reset amounts
             */
            $this->_setAmount(0);
            $this->_setBaseAmount(0);

            return $this;
        }

        $area = $this->helper->getCheckoutSession()->getMpArea();
        if ($area) {
            $this->getApplyRule($quote, $area);
        }
        $this->calculateAutoExtraFee($quote);

        if (empty($this->helper->getMpExtraFee($quote))) {
            return $this;
        }

        $extraFee  = $this->helper->getMpExtraFee($quote);
        $applyRule = $this->getAllApplyRule($quote);

        $ruleIds     = array_keys($extraFee);
        $loadedRules = $this->bulkLoadRules($ruleIds);

        foreach ($extraFee as $ruleId => $option) {
            if (!isset($applyRule[$ruleId]) || !isset($loadedRules[$ruleId])) {
                continue;
            }
            /** @var Rule $rule */
            $rule     = $loadedRules[$ruleId];
            $options  = $rule->getOptions() ? Data::jsonDecode($rule->getOptions())['option']['value'] : [];
            $taxClass = $rule->getFeeTax();

            if (is_array($option)) {
                foreach ($option as $item) {
                    [$baseRuleFeeAmount, $ruleFeeAmount, $baseRuleFeeAmountInclTax, $ruleFeeAmountInclTax]
                        = $this->calculateExtraFeeAmount($quote, $options[$item], $taxClass, $rule);
                    $code = "mp_extra_fee_rule_{$ruleId}_{$item}";
                    $this->setCode($code);
                    $this->_addAmount($ruleFeeAmountInclTax);
                    $this->_addBaseAmount($baseRuleFeeAmountInclTax);

                    // Cache the results
                    $this->calculatedFeesCache[$code] = [
                        'amount'      => $ruleFeeAmountInclTax,
                        'base_amount' => $baseRuleFeeAmountInclTax
                    ];
                }
            } else {
                [$baseRuleFeeAmount, $ruleFeeAmount, $baseRuleFeeAmountInclTax, $ruleFeeAmountInclTax]
                    = $this->calculateExtraFeeAmount($quote, $options[$option], $taxClass, $rule);
                $code = "mp_extra_fee_rule_{$ruleId}_{$option}";
                $this->setCode($code);
                $this->_addAmount(round($ruleFeeAmountInclTax, 2));
                $this->_addBaseAmount(round($baseRuleFeeAmountInclTax, 2));

                // Cache the results
                $this->calculatedFeesCache[$code] = [
                    'amount'      => $ruleFeeAmountInclTax,
                    'base_amount' => $baseRuleFeeAmountInclTax
                ];
            }
        }

        return $this;
    }

    /**
     * @param Quote $quote
     * @param mixed $area
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getApplyRule($quote, $area)
    {
        $area            = explode(',', $area);
        $applyRule       = [];
        $selectedOptions = [];
        foreach ($area as $item) {
            [$applyRule[], $selectedOptions[]] = $this->checkApplyRule($quote, $item);
        }
        $this->helper->getCheckoutSession()->setMpExtraFee([$applyRule, $selectedOptions]);
    }

    /**
     * @param Quote|Address $quote
     * @param mixed $area
     * @param bool $isMultiShipping
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function checkApplyRule($quote, $area, $isMultiShipping = false)
    {
        $cacheKey = $this->getValidationCacheKey($quote, $area, $isMultiShipping);
        if (isset($this->validatedRulesCache[$cacheKey])) {
            return $this->validatedRulesCache[$cacheKey];
        }

        $backendModelSession = $this->backendModelSession->getQuote();
        $customerGroupId     = $this->getCustomerGroupId($backendModelSession);
        $storeId             = $quote->getStoreId();

        $minimalRules = $this->helper->getMinimalRuleData($customerGroupId, $storeId, $area, $quote);

        if (empty($minimalRules)) {
            $result                               = [[], []];
            $this->validatedRulesCache[$cacheKey] = $result;

            return $result;
        }

        $defaults     = [];
        $applyRule    = [];
//        $address      = $isMultiShipping ? $quote : $this->helper->getAddressToValidate($quote, $this->shippingAssignment);
        $validRuleIds = [];
        foreach ($minimalRules as $ruleData) {
            $validRuleIds[] = $ruleData['rule_id'];

            if ($ruleData['stop_further_processing'] && $ruleData['apply_type'] == 1) {
                break;
            }
        }

        if (!empty($validRuleIds)) {
            $fullRuleCollection = $this->helper->getFilteredRuleCollection($customerGroupId, $storeId, $area);
            $filteredRules      = [];
            foreach ($fullRuleCollection as $rule) {
                if (in_array($rule->getId(), $validRuleIds)) {
                    $filteredRules[] = $rule;
                }
            }

            foreach ($filteredRules as $rule) {
                if ($rule->getArea() === $area) {
                    $options = Data::jsonDecode($rule->getOptions());
                    $default = $options['default'] ?? null;
                    if ($default) {
                        $defaults[$rule->getId()] = $default[0];
                    }
                }

                if ((int) $rule->getApplyType() === ApplyType::AUTOMATIC || $rule->getArea() !== $area) {
                    if ($rule->getStopFurtherProcessing()) {
                        break;
                    }
                    continue;
                }

                $options = Data::jsonDecode($rule->getOptions());
                if (!isset($options['option']['value']) ||
                    empty($options['option']['value']) ||
                    !is_array($options['option']['value'])) {
                    continue;
                }
                $this->calculateRuleFeesOptimized($quote, $rule, $options);

                if ($this->hasValidFeeAmount($options['option']['value'])) {
                    $rule->setOptions(Data::jsonEncode($options));
                    $applyRule[] = $rule->getData();

                    if ($rule->getStopFurtherProcessing()) {
                        break;
                    }
                }
            }
        }

        if (!$this->helper->getMpExtraFee($quote, $area)) {
            $this->helper->setMpExtraFee($quote, http_build_query(['rule' => $defaults]), $area);
        }

        $selectedOptions = $this->helper->getMpExtraFee($quote, $area);

        usort($applyRule, function ($aSort, $bSort) {
            return ($aSort['sort_order'] <= $bSort['sort_order']) ? -1 : 1;
        });

        $result = [$applyRule, $selectedOptions];

        $this->validatedRulesCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * @param Quote|Address $quote
     * @param Rule $rule
     * @param array $options
     */
    private function calculateRuleFeesOptimized($quote, $rule, &$options)
    {
        $quoteKey = $quote->getId() ?: 'temp';
        $ruleKey  = $rule->getId();
        $cacheKey = $quoteKey . '_' . $ruleKey;

        if (isset($this->calculatedFeesCache[$cacheKey])) {
            $cachedResults = $this->calculatedFeesCache[$cacheKey];
            foreach ($options['option']['value'] as $index => &$option) {
                if (isset($cachedResults[$index])) {
                    $option['calculated_amount']          = $cachedResults[$index]['amount'];
                    $option['calculated_amount_incl_tax'] = $cachedResults[$index]['amount_incl_tax'];
                }
            }
            unset($option);

            return;
        }

        $calculatedResults = [];
        foreach ($options['option']['value'] as $index => &$option) {
            [$baseRuleFeeAmount, $ruleFeeAmount, $baseRuleFeeAmountInclTax, $ruleFeeAmountInclTax]
                = $this->calculateExtraFeeAmount($quote, $option, $rule->getFeeTax(), $rule);

            $option['calculated_amount']          = $ruleFeeAmount;
            $option['calculated_amount_incl_tax'] = $ruleFeeAmountInclTax;

            $calculatedResults[$index] = [
                'amount'          => $ruleFeeAmount,
                'amount_incl_tax' => $ruleFeeAmountInclTax
            ];
        }
        unset($option);
        $this->calculatedFeesCache[$cacheKey] = $calculatedResults;
    }

    /**
     * Generate cache key for rule validation
     *
     * @param Quote|Address $quote
     * @param mixed $area
     * @param bool $isMultiShipping
     *
     * @return string
     */
    private function getValidationCacheKey($quote, $area, $isMultiShipping)
    {
        $quoteId       = $quote->getId() ?: 'temp';
        $addressData   = '';
        $paymentMethod = '';

        if (!$isMultiShipping) {
            $address     = $this->helper->getAddressToValidate($quote, $this->shippingAssignment);
            $addressData = hash('sha256', $this->serializer->serialize([
                'country_id' => $address->getCountryId(),
                'region_id'  => $address->getRegionId(),
                'postcode'   => $address->getPostcode(),
                'city'       => $address->getCity()
            ]));
        }

        if ($quote->getPayment() && $quote->getPayment()->getMethod()) {
            $paymentMethod = $quote->getPayment()->getMethod();
        }

        $quoteStateHash = hash('sha256', $this->serializer->serialize([
            'subtotal'         => $quote->getBaseSubtotal(),
            'grand_total'      => $quote->getGrandTotal(),
            'items_count'      => $quote->getItemsCount(),
            'items_qty'        => $quote->getItemsQty(),
            'quote_updated_at' => $quote->getUpdatedAt(),
            'payment_method'   => $paymentMethod
        ]));

        return sprintf(
            'validation_%s_%s_%d_%s_%s',
            $quoteId,
            $area,
            (int) $isMultiShipping,
            $addressData,
            $quoteStateHash
        );
    }

    /**
     * Get customer group ID with caching
     *
     * @param mixed $backendModelSession
     *
     * @return int
     */
    private function getCustomerGroupId($backendModelSession)
    {
        $cacheKey = 'customer_group_' . session_id();

        if (isset($this->customerGroupCache[$cacheKey])) {
            return $this->customerGroupCache[$cacheKey];
        }

        if ($this->customerSession->isLoggedIn()) {
            $customerGroupId = $this->customerSession->getCustomerGroupId();
        } elseif ($backendModelSession->getId()) {
            $customerGroupId = $backendModelSession->getCustomerGroupId();
        } else {
            $customerGroupId = 0;
        }

        $this->customerGroupCache[$cacheKey] = $customerGroupId;

        return $customerGroupId;
    }

    /**
     * Check if any option has valid fee amount
     *
     * @param array $options
     *
     * @return bool
     */
    private function hasValidFeeAmount($options)
    {
        foreach ($options as $option) {
            if (($option['calculated_amount'] ?? 0) > 0 ||
                ($option['calculated_amount_incl_tax'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $quote
     * @param $option
     * @param $taxClass
     * @param $rule
     *
     * @return array
     */
    public function calculateExtraFeeAmount($quote, $option, $taxClass, $rule = null)
    {
        if ($rule && is_object($rule)) {
            $cachedResult = $this->getCachedCalculation($quote, $rule->getId(), $option, $taxClass, $rule);
            if ($cachedResult !== null) {
                return $cachedResult;
            }
        }
        if (is_object($option)) {
            $type   = $option->getApplyFor() == 1 ? $option->getFeeType() : $option->getFeeTypeItem();
            $amount = $option->getAmount();
        } else {
            $type   = $option['type'];
            $amount = $option['amount'];
        }

        if ($this->customerSession->isLoggedIn()) {
            $customer         = $this->customerSession->getCustomer();
            $customerId       = $customer->getId();
            $customerTaxClass = $customer->getTaxClassId();
        } else {
            $customerId       = null;
            $customerTaxClass = null;
        }
        $rateRequest = $this->calculation->getRateRequest(
            $quote->getShippingAddress(),
            $quote->getBillingAddress(),
            $customerTaxClass,
            $quote->getStoreId(),
            $customerId
        );

        $rateRequest->setProductClassId($taxClass);
        $taxRate = $this->calculation->getRate($rateRequest);

        switch ($type) {
            case FeeType::FIX_AMOUNT_FOR_WHOLE_CART:
                $baseRuleFeeAmount = $amount;
                break;
            case FeeType::PERCENTAGE_OF_CART_TOTAL:
                $ruleObject = $rule;
                if (!$ruleObject && is_object($option)) {
                    $ruleObject = $option;
                }
                $taxAmount        = 0;
                $calculateOptions = $this->checkCalculateOptions($ruleObject ?: $option);

                $baseRuleFeeAmount = $quote->getBaseSubtotal();
                if ($quote instanceof Address) {
                    $shippingAmount      = $quote->getBaseShippingAmount();
                    $baseShippingInclTax = $quote->getBaseShippingInclTax();
                } else {
                    $shippingAmount      = $quote->getShippingAddress()->getBaseShippingAmount();
                    $baseShippingInclTax = $quote->getShippingAddress()->getBaseShippingInclTax();
                }
                if ($taxRate == 0) {
                    $baseShippingInclTax = $shippingAmount;
                }

                $optimizedItems = $this->getOptimizedQuoteItems($quote);
                $taxAmount      = $optimizedItems['tax_amount'];
                $totalInclTax   = $quote->getBaseSubtotal() + $taxAmount;

                $baseRuleFeeAmount = $this->calculateOptionsFee(
                    $quote,
                    $baseRuleFeeAmount,
                    $quote->getBaseSubtotal(),
                    $calculateOptions,
                    $baseShippingInclTax,
                    $totalInclTax,
                    $quote->getBaseSubtotalWithDiscount(),
                    $shippingAmount
                );
                $baseRuleFeeAmount *= ($amount / 100);
                break;
            case FeeTypeItem::FIX_AMOUNT_FOR_ITEM:
                $ruleObject = $rule;
                if (!$ruleObject && is_object($option)) {
                    $ruleObject = $option;
                }

                // Fixed amount per item - no shipping/discount/tax calculations applied
                [$baseRuleFeeAmount] = $this->calculateItemBasedFee($quote, $ruleObject, 'fixed', $amount);
                break;
            case FeeTypeItem::PERCENTAGE_ITEM_AMOUNT:
                $ruleObject = $rule;
                if (!$ruleObject && is_object($option)) {
                    $ruleObject = $option;
                }

                $calculateOptions = $this->checkCalculateOptions($ruleObject);

                if ($quote instanceof Address) {
                    $shippingAmount      = $quote->getBaseShippingAmount();
                    $baseShippingInclTax = $quote->getBaseShippingInclTax();
                } else {
                    $shippingAmount      = $quote->getShippingAddress()->getBaseShippingAmount();
                    $baseShippingInclTax = $quote->getShippingAddress()->getBaseShippingInclTax();
                }
                if ($taxRate == 0) {
                    $baseShippingInclTax = $shippingAmount;
                }

                // For percentage item amount, calculate percentage on total including shipping/discount/tax if configured
                $baseRuleFeeAmount = $this->calculatePercentageItemBasedFeeWithOptions(
                    $quote,
                    $ruleObject,
                    $amount,
                    $calculateOptions,
                    $shippingAmount,
                    $baseShippingInclTax
                );
                break;
            default:
                $qtyOrdered = $quote->getItemsQty();
                if ($quote instanceof Address) {
                    $qtyOrdered = $quote->getItemQty();
                    if (!$qtyOrdered && $quote->getQuote()->hasVirtualItems()) {
                        $optimizedItems = $this->getOptimizedQuoteItems($quote);
                        $qtyOrdered     = $optimizedItems['total_qty'];
                    }
                }

                $baseRuleFeeAmount = $amount * $qtyOrdered;
                break;
        }

        $baseTaxAmount            = $baseRuleFeeAmount * $taxRate / 100;
        $baseRuleFeeAmountInclTax = $baseRuleFeeAmount + ($baseRuleFeeAmount * $taxRate / 100);
        $ruleFeeAmount            = $this->priceCurrency->convert($baseRuleFeeAmount, $quote->getStore());
        $ruleFeeAmountInclTax     = $this->priceCurrency->convert($baseRuleFeeAmountInclTax, $quote->getStore());
        $taxAmount                = $ruleFeeAmount * $taxRate / 100;

        $result = [
            round($baseRuleFeeAmount, 2),
            round($ruleFeeAmount, 2),
            round($baseRuleFeeAmountInclTax, 2),
            round($ruleFeeAmountInclTax, 2),
            round($baseTaxAmount, 2),
            round($taxAmount, 2)
        ];

        if ($rule && is_object($rule)) {
            $this->setCachedCalculation($quote, $rule->getId(), $option, $taxClass, $rule, $result);
        }

        return $result;
    }

    /**
     * @param $rule
     *
     * @return array|string[]
     */
    protected function checkCalculateOptions($rule)
    {
        $calculateOptions = $this->calculateOptions($this->helper->getConfigGeneral('calculate_options'));

        if (is_object($rule) && $rule->getData('fee_include') && $rule->getFeeInclude() != 'mp-use-config') {
            $calculateOptions = $this->calculateOptions($rule->getFeeInclude());
        }

        return $calculateOptions;
    }

    /**
     * @param $quote
     * @param $baseRuleFeeAmount
     * @param $ruleFeeAmountExclTax
     * @param $calculateOptions
     * @param $baseShippingInclTax
     * @param $totalInclTax
     * @param $amountWithDiscount
     * @param $shippingAmount
     *
     * @return mixed
     */
    protected function calculateOptionsFee(
        $quote,
        $baseRuleFeeAmount,
        $ruleFeeAmountExclTax,
        $calculateOptions,
        $baseShippingInclTax,
        $totalInclTax,
        $amountWithDiscount,
        $shippingAmount
    ) {
        if (in_array(CalculateOptions::TAX, $calculateOptions, false)) {
            $baseRuleFeeAmount = $totalInclTax;
            $shippingAmount    = $baseShippingInclTax;
        }
        if (in_array(CalculateOptions::DISCOUNT, $calculateOptions, false)) {
            if ($quote->getBaseSubtotalWithDiscount() != 0) {
                $baseRuleFeeAmount += $amountWithDiscount - $ruleFeeAmountExclTax;
            } else {
                $baseRuleFeeAmount += $amountWithDiscount - $totalInclTax;
            }
        }
        if (in_array(CalculateOptions::SHIPPING_FEE, $calculateOptions, false)) {
            $baseRuleFeeAmount += $shippingAmount;
        }

        return $baseRuleFeeAmount;
    }

    /**
     * @param $option
     *
     * @return array|string[]
     */
    protected function calculateOptions($option)
    {
        return $option
            ? explode(',', $option)
            : [];
    }

    /**
     * @param Quote|Address $quote
     * @param bool $isFetch
     * @param bool $isMultiShipping
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function calculateAutoExtraFee($quote, $isFetch = false, $isMultiShipping = false)
    {
        $backendModelSession = $this->backendModelSession->getQuote();

        $ruleCollection = $this->helper->getRuleCollection();

        $result = [];
        /** @var Rule $rule */
        foreach ($ruleCollection as $rule) {
            $stores         = explode(',', $rule->getStoreIds());
            $customerGroups = explode(',', $rule->getCustomerGroups());
            if ($this->customerSession->isLoggedIn()) {
                $customerGroupId = $this->customerSession->getCustomerGroupId();
            } elseif ($backendModelSession->getId()) {
                $customerGroupId = $backendModelSession->getCustomerGroupId();
            } else {
                $customerGroupId = 0;
            }

            if (!$isMultiShipping) {
                $storeId = $quote->getStoreId();
            } else {
                $storeId = $quote->getQuote()->getStoreId();
            }

            if (!$rule->getStatus()
                || !in_array($customerGroupId, $customerGroups, false)
                || !(in_array($storeId, $stores, false) || in_array('0', $stores, true))
            ) {
                continue;
            }

            $address = $quote;
            if (!$isMultiShipping) {
                $address = $this->helper->getAddressToValidate($quote, $this->shippingAssignment);
            }

            if ($rule->validate($address, $isFetch)) {
                if ((int) $rule->getApplyType() !== ApplyType::AUTOMATIC) {
                    if ($rule->getStopFurtherProcessing()) {
                        break;
                    }
                    continue;
                }

                $taxClass = $rule->getFeeTax();
                $rule->setType($rule->getFeeType());
                [$baseRuleFeeAmount, $ruleFeeAmount, $baseRuleFeeAmountInclTax, $ruleFeeAmountInclTax, $baseTax, $tax]
                    = $this->calculateExtraFeeAmount($quote, $rule, $taxClass);
                if ($baseRuleFeeAmount == 0 && $baseRuleFeeAmountInclTax == 0) {
                    continue;
                }
                if ($isFetch) {
                    $result[] = $this->fetchAutoFee(
                        $rule,
                        $baseRuleFeeAmount,
                        $ruleFeeAmount,
                        $baseRuleFeeAmountInclTax,
                        $ruleFeeAmountInclTax,
                        $quote,
                        $taxClass
                    );
                } else {
                    $this->addAutoFeeToTotal($rule, $ruleFeeAmountInclTax, $baseRuleFeeAmountInclTax, $baseTax, $tax);
                }
                if ($rule->getStopFurtherProcessing()) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param $rule
     * @param $baseRuleFeeAmount
     * @param $ruleFeeAmount
     * @param $baseRuleFeeAmountInclTax
     * @param $ruleFeeAmountInclTax
     * @param $quote
     * @param $taxClass
     *
     * @return array
     */
    protected function fetchAutoFee(
        $rule,
        $baseRuleFeeAmount,
        $ruleFeeAmount,
        $baseRuleFeeAmountInclTax,
        $ruleFeeAmountInclTax,
        $quote,
        $taxClass
    ) {
        $this->helper->getConfigValue('');
        $label   = isset(Data::jsonDecode($rule->getLabels())[$quote->getStoreId()])
            ? (Data::jsonDecode($rule->getLabels())[$quote->getStoreId()] ?: $rule->getName()) : $rule->getName();
        $taxRate = $this->calculationRate($quote, $taxClass);

        return [
            'code'                => "mp_extra_fee_rule_{$rule->getId()}_auto",
            'title'               => __($label),
            'label'               => __($label),
            'value'               => $ruleFeeAmount,
            'value_excl_tax'      => $ruleFeeAmount,
            'value_incl_tax'      => $ruleFeeAmountInclTax,
            'base_value'          => $baseRuleFeeAmount,
            'base_value_incl_tax' => $baseRuleFeeAmountInclTax,
            'rf'                  => $rule->getRefundable(),
            'display_area'        => '3',
            'apply_type'          => $rule->getApplyType(),
            'rule_label'          => $this->helper->getRuleLabel($rule, $quote->getStoreId()),
            'percent'             => $taxRate
        ];
    }

    /**
     * @param $rule
     * @param $ruleFeeAmount
     * @param $baseRuleFeeAmount
     * @param $baseTax
     * @param $tax
     *
     * @return void
     */
    protected function addAutoFeeToTotal($rule, $ruleFeeAmount, $baseRuleFeeAmount, $baseTax, $tax)
    {
        $this->setCode("mp_extra_fee_rule_{$rule->getId()}_auto");
        $this->_addAmount($ruleFeeAmount);
        $this->_addBaseAmount($baseRuleFeeAmount);
        $this->total->setBaseTaxAmount($this->total->getBaseTaxAmount() + $baseTax);
        $this->total->setTaxAmount($this->total->getTaxAmount() + $tax);
    }

    /**
     * @param Quote $quote
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAllApplyRule($quote)
    {
        $backendModelSession = $this->backendModelSession->getQuote();
        $ruleCollection      = $this->helper->getRuleCollection();
        $applyRule           = [];

        /** @var Rule $rule */
        foreach ($ruleCollection as $rule) {
            $stores         = explode(',', $rule->getStoreIds());
            $customerGroups = explode(',', $rule->getCustomerGroups());
            if ($this->customerSession->isLoggedIn()) {
                $customerGroupId = $this->customerSession->getCustomerGroupId();
            } elseif ($backendModelSession->getId()) {
                $customerGroupId = $backendModelSession->getCustomerGroupId();
            } else {
                $customerGroupId = 0;
            }
            if (!$rule->getStatus()
                || !in_array($customerGroupId, $customerGroups, false)
                || !(in_array($quote->getStoreId(), $stores, false) || in_array('0', $stores, true))
            ) {
                continue;
            }
            $address = $this->helper->getAddressToValidate($quote, $this->shippingAssignment);
            if ($rule->validate($address)) {
                if ((int) $rule->getApplyType() === ApplyType::AUTOMATIC) {
                    if ($rule->getStopFurtherProcessing()) {
                        break;
                    }
                    continue;
                }

                $applyRule[$rule->getId()] = $rule->getData();
                if ($rule->getStopFurtherProcessing()) {
                    break;
                }
            }
        }

        return $applyRule;
    }

    /**
     * Assign extra fee amount and label to address object
     *
     * @param Quote $quote
     * @param Address\Total $total
     *
     * @return array
     * @throws Exception
     */
    public function fetch(Quote $quote, Total $total)
    {
        $fullActionName = $this->request->getFullActionName();
        if (!$this->helper->isEnabled() || in_array($fullActionName, [
                'multishipping_checkout_overview',
                'multishipping_checkout_overviewPost'
            ], true)) {
            $result             = [];
            $extraFee           = $this->helper->getMpExtraFee($total, 4);
            $extraFeeAmount     = 0;
            $baseExtraFeeAmount = 0;
            foreach ($extraFee as $ruleId => $option) {
                $extraFeeAmount     += $option['value'];
                $baseExtraFeeAmount += $option['base_value'];
                $option['title']    = $option['rule_label'] . (strpos($option['code'], 'auto') === false
                        ? ' - ' . $option['label'] : $option['label']);
                $result[]           = $option;
                $quote->setGrandTotal($quote->getGrandTotal() + $option['value_incl_tax']);
                $quote->setBaseGrandTotal($quote->getBaseGrandTotal() + $option['base_value_incl_tax']);
            }

            $total->setGrandTotal($total->getGrandTotal() + $extraFeeAmount);
            $total->setBaseGrandTotal($total->getBaseGrandTotal() + $baseExtraFeeAmount);

            $addresses = $quote->getAllShippingAddresses();
            /** @var Address $address */
            foreach ($addresses as $address) {
                if ($address->getId() === $total->getAddressId()) {
                    $address->setGrandTotal($total->getGrandTotal());
                    $address->setBaseGrandTotal($total->getBaseGrandTotal());

                    $address->save();
                }
            }

            $quote->save();

            return $result;
        }

        $result   = $this->calculateAutoExtraFee($quote, true);
        $extraFee = $this->helper->getMpExtraFee($quote);

        if (empty($extraFee)) {
            $this->helper->setMpExtraFee($quote, $result, DisplayArea::TOTAL);

            return $result;
        }
        $applyRule = $this->getAllApplyRule($quote);

        $ruleIds     = array_keys($extraFee);
        $loadedRules = $this->bulkLoadRules($ruleIds);

        foreach ($extraFee as $ruleId => $option) {
            if (!isset($applyRule[$ruleId]) || !isset($loadedRules[$ruleId])) {
                continue;
            }
            /** @var Rule $rule */
            $rule     = $loadedRules[$ruleId];
            $options  = $rule->getOptions() ? Data::jsonDecode($rule->getOptions())['option']['value'] : [];
            $taxClass = $rule->getFeeTax();
            $taxRate  = $this->calculationRate($quote, $taxClass);

            if (is_array($option)) {
                foreach ($option as $item) {
                    [$baseRuleFeeAmount, $ruleFeeAmount, $baseRuleFeeAmountInclTax, $ruleFeeAmountInclTax] =
                        $this->calculateExtraFeeAmount($quote, $options[$item], $taxClass, $rule);
                    $result[] = [
                        'code'                => "mp_extra_fee_rule_{$ruleId}_{$item}",
                        'title'               => __($options[$item][$quote->getStoreId()] ?: $options[$item][0]),
                        'label'               => __($options[$item][$quote->getStoreId()] ?: $options[$item][0]),
                        'value'               => $ruleFeeAmount,
                        'base_value'          => $baseRuleFeeAmount,
                        'base_value_incl_tax' => $baseRuleFeeAmountInclTax,
                        'value_incl_tax'      => $ruleFeeAmountInclTax,
                        'value_excl_tax'      => $ruleFeeAmount,
                        'rf'                  => $rule->getRefundable(),
                        'display_area'        => $rule->getArea() ?: '3',
                        'apply_type'          => $rule->getApplyType(),
                        'rule_label'          => $this->helper->getRuleLabel($rule, $quote->getStoreId()),
                        'percent'             => $taxRate
                    ];
                }
            } else {
                [$baseRuleFeeAmount, $ruleFeeAmount, $baseRuleFeeAmountInclTax, $ruleFeeAmountInclTax] =
                    $this->calculateExtraFeeAmount($quote, $options[$option], $taxClass, $rule);
                $result[] = [
                    'code'                => "mp_extra_fee_rule_{$ruleId}_{$option}",
                    'title'               => __($options[$option][$quote->getStoreId()] ?: $options[$option][0]),
                    'label'               => __($options[$option][$quote->getStoreId()] ?: $options[$option][0]),
                    'value'               => $ruleFeeAmount,
                    'base_value'          => $baseRuleFeeAmount,
                    'value_incl_tax'      => $ruleFeeAmountInclTax,
                    'value_excl_tax'      => $ruleFeeAmount,
                    'base_value_incl_tax' => $baseRuleFeeAmountInclTax,
                    'rf'                  => $rule->getRefundable(),
                    'display_area'        => $rule->getArea() ?: '3',
                    'apply_type'          => $rule->getApplyType(),
                    'rule_label'          => $this->helper->getRuleLabel($rule, $quote->getStoreId()),
                    'percent'             => $taxRate
                ];
            }
        }
        $this->helper->setMpExtraFee($quote, $result, DisplayArea::TOTAL);
        $quote->save();

        return $result;
    }

    /**
     * @param $quote
     * @param $taxClass
     *
     * @return float|int
     */
    protected function calculationRate($quote, $taxClass)
    {
        if ($this->customerSession->isLoggedIn()) {
            $customer         = $this->customerSession->getCustomer();
            $customerId       = $customer->getId();
            $customerTaxClass = $customer->getTaxClassId();
        } else {
            $customerId       = null;
            $customerTaxClass = null;
        }
        $rateRequest = $this->calculation->getRateRequest(
            $quote->getShippingAddress(),
            $quote->getBillingAddress(),
            $customerTaxClass,
            $quote->getStoreId(),
            $customerId
        );
        $rateRequest->setProductClassId($taxClass);

        return $this->calculation->getRate($rateRequest);
    }

    /**
     * Bulk load rules to avoid N+1 queries
     *
     * @param array $ruleIds
     *
     * @return array
     */
    private function bulkLoadRules(array $ruleIds)
    {
        $cacheKey = 'bulk_rules_' . implode('_', $ruleIds);

        if (isset($this->loadedRulesCache[$cacheKey])) {
            return $this->loadedRulesCache[$cacheKey];
        }

        $loadedRules = [];
        $rulesToLoad = [];

        foreach ($ruleIds as $ruleId) {
            $individualCacheKey = 'rule_' . $ruleId;
            if (isset($this->loadedRulesCache[$individualCacheKey])) {
                $loadedRules[$ruleId] = $this->loadedRulesCache[$individualCacheKey];
            } else {
                $rulesToLoad[] = $ruleId;
            }
        }

        if (!empty($rulesToLoad)) {
            $ruleCollection = $this->ruleFactory->create()->getCollection()
                ->addFieldToFilter('rule_id', ['in' => $rulesToLoad]);

            foreach ($ruleCollection as $rule) {
                $loadedRules[$rule->getId()]                      = $rule;
                $this->loadedRulesCache['rule_' . $rule->getId()] = $rule;
            }
        }

        $this->loadedRulesCache[$cacheKey] = $loadedRules;

        return $loadedRules;
    }

    /**
     * Get optimized quote items with caching
     *
     * @param Quote|Address $quote
     *
     * @return array
     */
    private function getOptimizedQuoteItems($quote)
    {
        $quoteId = $quote->getId() ?: 'temp';

        if (isset($this->quoteItemsCache[$quoteId])) {
            return $this->quoteItemsCache[$quoteId];
        }

        $items = [
            'all_visible' => [],
            'filtered'    => [],
            'tax_amount'  => 0,
            'total_qty'   => 0
        ];

        foreach ($quote->getAllVisibleItems() as $item) {
            $items['all_visible'][] = $item;
            $items['tax_amount']    += $item->getTaxAmount();
            $items['total_qty']     += $item->getQty();

            $productType = $item->getProductType();
            if ($productType !== 'configurable' && $productType !== 'bundle') {
                $items['filtered'][] = $item;
            }
        }

        $this->quoteItemsCache[$quoteId] = $items;

        return $items;
    }

    /**
     * Calculate fee amount for items with optimized iteration
     *
     * @param Quote|Address $quote
     * @param mixed $ruleObject
     * @param string $calculationType
     * @param float $amount
     *
     * @return array [baseAmount, discount, taxAmount]
     */
    private function calculateItemBasedFee($quote, $ruleObject, $calculationType, $amount)
    {
        $optimizedItems    = $this->getOptimizedQuoteItems($quote);
        $baseRuleFeeAmount = 0;
        $discount          = 0;
        $taxAmount         = 0;

        $itemsToProcess = $optimizedItems['all_visible'];

        foreach ($itemsToProcess as $item) {
            $shouldApplyFee = true;

            if ($ruleObject && is_object($ruleObject) && method_exists($ruleObject, 'validateActionsRule')) {
                $idCache = $item->getId() ? $item->getId() : $item->getProductId();
                $validationKey = $ruleObject->getId() . '_' . $idCache;
                if (isset($this->validationResultsCache[$validationKey])) {
                    $shouldApplyFee = $this->validationResultsCache[$validationKey];
                } else {
                    /** @var \Mageplaza\ExtraFee\Model\Rule $ruleObject */
                    $shouldApplyFee                               = $ruleObject->validateActionsRule($item);
                    $this->validationResultsCache[$validationKey] = $shouldApplyFee;
                }
            }

            if ($shouldApplyFee) {
                if ($calculationType === 'percentage') {
                    $baseRuleFeeAmount += $item->getPrice() * $item->getQty();
                    $discount          += $item->getDiscountAmount();
                    $taxAmount         += $item->getTaxAmount();
                } else {
                    // Fixed amount per unique item (not per quantity)
                    $baseRuleFeeAmount += $amount;
                }
            }
        }

        return [$baseRuleFeeAmount, $discount, $taxAmount];
    }

    /**
     * Calculate percentage-based fee per unique item (unit prices) with shipping/discount/tax handling
     *
     * @param Quote|Address $quote
     * @param mixed $ruleObject
     * @param float $percentageAmount
     * @param array $calculateOptions
     * @param float $shippingAmount
     * @param float $baseShippingInclTax
     *
     * @return float
     */
    private function calculatePercentageItemBasedFeeWithOptions(
        $quote,
        $ruleObject,
        $percentageAmount,
        $calculateOptions,
        $shippingAmount,
        $baseShippingInclTax
    ) {
        $optimizedItems = $this->getOptimizedQuoteItems($quote);
        $baseItemAmount = 0;

        foreach ($optimizedItems['all_visible'] as $item) {
            $shouldApplyFee = true;

            if ($ruleObject && is_object($ruleObject) && method_exists($ruleObject, 'validateActionsRule')) {
                $idCache = $item->getId() ? $item->getId() : $item->getProductId();
                $validationKey = $ruleObject->getId() . '_' . $idCache;
                if (isset($this->validationResultsCache[$validationKey])) {
                    $shouldApplyFee = $this->validationResultsCache[$validationKey];
                } else {
                    $shouldApplyFee                               = $ruleObject->validateActionsRule($item);
                    $this->validationResultsCache[$validationKey] = $shouldApplyFee;
                }
            }

            if ($shouldApplyFee) {
                $baseItemAmount += $item->getPrice();
            }
        }

        $totalAmountForPercentage = $baseItemAmount;

        if (!empty($calculateOptions)) {
            if (in_array(CalculateOptions::SHIPPING_FEE, $calculateOptions, false)) {
                $totalAmountForPercentage += $shippingAmount;
            }

            if (in_array(CalculateOptions::DISCOUNT, $calculateOptions, false)) {
                // Subtract discount amount (since discount reduces the total)
                $discountAmount           = $quote->getBaseSubtotal() - $quote->getBaseSubtotalWithDiscount();
                $totalAmountForPercentage -= $discountAmount;
            }

            if (in_array(CalculateOptions::TAX, $calculateOptions, false)) {
                $taxAmount                = $optimizedItems['tax_amount'];
                $totalAmountForPercentage += $taxAmount;
            }
        }

        return $totalAmountForPercentage * ($percentageAmount / 100);
    }
}
