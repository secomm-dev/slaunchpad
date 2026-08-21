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

namespace Mageplaza\ExtraFee\Helper;

use DateTime;
use DateTimeZone;
use Magento\Backend\Model\Session\Quote;
use Magento\Checkout\Model\Cart as CheckoutCart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\SessionFactory as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Config;
use Mageplaza\Core\Helper\AbstractData as CoreHelper;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;
use Mageplaza\ExtraFee\Model\ResourceModel\Rule\Collection;
use Mageplaza\ExtraFee\Model\ResourceModel\Rule\CollectionFactory;
use Mageplaza\ExtraFee\Model\Rule;
use Mageplaza\ExtraFee\Model\RuleFactory;

/**
 * Class Data
 * @package Mageplaza\ExtraFee\Helper
 */
class Data extends CoreHelper
{
    const CONFIG_MODULE_PATH = 'mp_extra_fee';

    /**
     * @var
     */
    protected $addressToValidate;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var CollectionFactory
     */
    protected $ruleCollectionFactory;

    /**
     * @var CheckoutCart
     */
    protected $cart;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var RuleFactory
     */
    protected $ruleFactory;

    /**
     * @var array
     */
    private $ruleCollectionCache = [];

    /**
     * @var array
     */
    private $filteredRuleCache = [];

    /**
     * @var array
     */
    private $currentTimeCache = [];

    /**
     * @var array
     */
    private static $staticRuleCache = [];

    /**
     * @var array
     */
    private $optimizedRuleCache = [];

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var array|null
     */
    private $activeAttributesCache = null;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param CollectionFactory $ruleCollectionFactory
     * @param CheckoutCart $cart
     * @param CustomerSession $customerSession
     * @param QuoteFactory $quoteFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param RuleFactory $ruleFactory
     * @param SerializerInterface $serializer
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        CollectionFactory $ruleCollectionFactory,
        CheckoutCart $cart,
        CustomerSession $customerSession,
        QuoteFactory $quoteFactory,
        ScopeConfigInterface $scopeConfig,
        RuleFactory $ruleFactory,
        SerializerInterface $serializer,
        ResourceConnection $resourceConnection
    ) {
        $this->checkoutSession       = $checkoutSession;
        $this->quoteRepository       = $quoteRepository;
        $this->ruleCollectionFactory = $ruleCollectionFactory;
        $this->cart                  = $cart;
        $this->quoteFactory          = $quoteFactory;
        $this->customerSession       = $customerSession;
        $this->scopeConfig           = $scopeConfig;
        $this->ruleFactory           = $ruleFactory;
        $this->serializer            = $serializer;
        $this->resourceConnection    = $resourceConnection;

        parent::__construct($context, $objectManager, $storeManager);
    }

    /**
     * @param null|QuoteModel $quote
     *
     * @return $this
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function collectTotals($quote = null)
    {
        if ($this->isAdmin()) {
            return $this;
        }

        if ($quote === null) {
            /** @var QuoteModel $quote */
            $quote = $this->getCheckoutSession()->getQuote();
        }

        $quote->getShippingAddress()->setCollectShippingRates(true);

        if (!$quote->getTotalsCollectedFlag()) {
            $this->quoteRepository->save($quote->collectTotals());
        } else {
            $this->quoteRepository->save($quote);
        }

        return $this;
    }

    /**
     * Get checkout session for admin and frontend
     *
     * @return CheckoutSession|mixed
     */
    public function getCheckoutSession()
    {
        if (!$this->checkoutSession) {
            $this->checkoutSession = $this->objectManager
                ->get($this->isAdmin() ? Quote::class : CheckoutSession::class);
        }

        return $this->checkoutSession;
    }

    /**
     * @param QuoteModel|Order|Address|Object $quote
     *
     * @return array|mixed
     */
    public function getExtraFeeTotals($quote)
    {
        $extraFee = $quote->getMpExtraFee() ? $this::jsonDecode($quote->getMpExtraFee()) : [];

        return isset($extraFee['totals']) ? $extraFee['totals'] : [];
    }

    /**
     * @param QuoteModel|Order|Address $quote
     * @param array|string $value
     * @param string $area
     */
    public function setMpExtraFee($quote, $value, $area)
    {
        $extraFee = $quote->getMpExtraFee() ? $this::jsonDecode($quote->getMpExtraFee()) : [];
        if (is_string($value)) {
            $this->setNoteToSession($value);
        }

        $checkoutSession = $this->getCheckoutSession();

        if (!isset($extraFee['summary'])) {
            $ruleCollection = $this->ruleCollectionFactory->create()->addFieldToFilter('area', '3');
            $defaults       = [];
            foreach ($ruleCollection as $rule) {
                $default = self::jsonDecode($rule->getOptions())['default'];
                if ($default) {
                    $defaults[$rule->getId()] = $default[0];
                }
            }
            $extraFee['summary'] = http_build_query(['rule' => $defaults]);
        }

        $extraFeeNote = $checkoutSession->getExtraFeeNote() ?: [];

        if (count($extraFeeNote) && is_string($value)) {
            $value = $this->setNoteToParams($extraFeeNote, $value);
        }

        switch ((int) $area) {
            case DisplayArea::PAYMENT_METHOD:
                $extraFee['payment'] = $value;
                break;
            case DisplayArea::SHIPPING_METHOD:
                $extraFee['shipping'] = $value;
                break;
            case DisplayArea::CART_SUMMARY:
                $extraFee['summary'] = $value;
                break;
            case DisplayArea::TOTAL:
                $extraFee['totals'] = $value;
        }

        $quote->setMpExtraFee($this::jsonEncode($extraFee))->save();
    }

    /**
     * @param Rule $rule
     * @param int $storeId
     *
     * @return string
     */
    public function getRuleLabel($rule, $storeId)
    {
        $labels = $rule->getlabels() ? $this::jsonDecode($rule->getlabels()) : [];

        return isset($labels[$storeId]) ? ($labels[$storeId] ?: $rule->getName()) : '';
    }

    /**
     * @param QuoteModel|Order $quote
     * @param int $invoiceId
     */
    public function setInvoiced($quote, $invoiceId)
    {
        $extraFee                = $quote->getMpExtraFee() ? $this::jsonDecode($quote->getMpExtraFee()) : [];
        $extraFee['is_invoiced'] = $invoiceId;
        $quote->setMpExtraFee($this::jsonEncode($extraFee))->save();
    }

    /**
     * @param QuoteModel|Order|Object $quote
     *
     * @return bool|mixed
     */
    public function isInvoiced($quote)
    {
        $extraFee = $quote->getMpExtraFee() ? $this::jsonDecode($quote->getMpExtraFee()) : [];

        return isset($extraFee['is_invoiced']) ? $extraFee['is_invoiced'] : false;
    }

    /**
     * @param Quote|Order $quote
     * @param int $creditmemoId
     */
    public function setRefunded($quote, $creditmemoId)
    {
        $extraFee                = $quote->getMpExtraFee() ? $this::jsonDecode($quote->getMpExtraFee()) : [];
        $extraFee['is_refunded'] = $creditmemoId;
        $quote->setMpExtraFee($this::jsonEncode($extraFee))->save();
    }

    /**
     * @param QuoteModel|Order|Object $quote
     *
     * @return bool|mixed
     */
    public function isRefunded($quote)
    {
        $extraFee = $quote->getMpExtraFee() ? $this::jsonDecode($quote->getMpExtraFee()) : [];

        return isset($extraFee['is_refunded']) ? $extraFee['is_refunded'] : false;
    }

    /**
     * @return bool
     */
    public function isDisabled()
    {
        return !$this->isEnabled();
    }

    /**
     * @return bool
     */
    public function isOscPage()
    {
        $moduleEnable = $this->isModuleOutputEnabled('Mageplaza_Osc');
        $isOscModule  = ($this->_request->getRouteName() === 'onestepcheckout');

        return $moduleEnable && $isOscModule && $this->isEnabled();
    }

    /**
     * @return mixed
     */
    public function getDefaultCountryId()
    {
        return $this->scopeConfig->getValue(
            Config::CONFIG_XML_PATH_DEFAULT_COUNTRY,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return mixed|null
     */
    public function getDefaultRegionId()
    {
        $defaultRegionId = $this->scopeConfig->getValue(
            Config::CONFIG_XML_PATH_DEFAULT_REGION,
            ScopeInterface::SCOPE_STORE
        );

        if (0 == $defaultRegionId) {
            $defaultRegionId = null;
        }

        return $defaultRegionId;
    }

    /**
     * @return mixed
     */
    public function getDefaultPostcode()
    {
        return $this->scopeConfig->getValue(
            Config::CONFIG_XML_PATH_DEFAULT_POSTCODE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @param Object $object
     * @param Order $order
     *
     * @return array
     */
    public function getObjectExtraFeeTotals($object, $order)
    {
        $extraFeeTotals = [];
        $extraFeeItems  = [];
        foreach ($order->getItems() as $orderItems) {
            if ($this->isBundleOrConfig($orderItems)) {
                continue;
            }
            foreach ($object->getItems() as $item) {
                if ($orderItems->getProductId() === $item->getProductId()) {
                    $mpExtraFees = Data::jsonDecode($orderItems->getMpExtraFee());
                    $item->setMpExtraFee($orderItems->getMpExtraFee());
                    foreach ($mpExtraFees as $mpExtraFee) {
                        $mpExtraFee['qty'] = $item->getQty() ? $item->getQty() : $item->getQtyOrdered();
                        $extraFeeItems[]   = $mpExtraFee;
                    }
                }
            }
        }

        foreach ($extraFeeItems as $extraFeeItem) {
            if (!isset($extraFeeTotals[$extraFeeItem['code']])) {
                $extraFeeTotals[$extraFeeItem['code']] = [
                    'code'                => $extraFeeItem['code'],
                    'title'               => $extraFeeItem['title'],
                    'label'               => $extraFeeItem['label'],
                    'value'               => $extraFeeItem['value'] * $extraFeeItem['qty'],
                    'base_value'          => $extraFeeItem['base_value'] * $extraFeeItem['qty'],
                    'value_incl_tax'      => $extraFeeItem['value_incl_tax'] * $extraFeeItem['qty'],
                    'value_excl_tax'      => $extraFeeItem['value_excl_tax'] * $extraFeeItem['qty'],
                    'base_value_incl_tax' => $extraFeeItem['base_value_incl_tax'] * $extraFeeItem['qty'],
                    'rf'                  => $extraFeeItem['rf'],
                    'display_area'        => $extraFeeItem['display_area'],
                    'apply_type'          => $extraFeeItem['apply_type'],
                    'rule_label'          => $extraFeeItem['rule_label']
                ];
            } else {
                $extraFeeTotals[$extraFeeItem['code']]['value']               += $extraFeeItem['value']
                    * $extraFeeItem['qty'];
                $extraFeeTotals[$extraFeeItem['code']]['base_value']          += $extraFeeItem['base_value']
                    * $extraFeeItem['qty'];
                $extraFeeTotals[$extraFeeItem['code']]['value_incl_tax']      += $extraFeeItem['value_incl_tax']
                    * $extraFeeItem['qty'];
                $extraFeeTotals[$extraFeeItem['code']]['value_excl_tax']      += $extraFeeItem['value_excl_tax']
                    * $extraFeeItem['qty'];
                $extraFeeTotals[$extraFeeItem['code']]['base_value_incl_tax'] += $extraFeeItem['base_value_incl_tax']
                    * $extraFeeItem['qty'];
            }
        }

        return $extraFeeTotals;
    }

    /**
     * @param $item
     *
     * @return bool
     */
    public function isBundleOrConfig($item)
    {
        $productType = $item->getProductType();
        if ($productType === 'configurable' || $productType === 'bundle') {
            return true;
        }

        return false;

    }

    /**
     * @param Order $order
     */
    protected function getTotalOrderedQty($order)
    {
        $totalQty = 0;

        foreach ($order->getItems() as $item) {
            if ($this->isBundleOrConfig($item)) {
                continue;
            }
            $totalQty += $item->getQtyOrdered();
        }

        return $totalQty;
    }

    /**
     * @param Order $order
     * @param Quote|Address $quote
     */
    public function setExtraFeeForItems($order, $quote)
    {
        $order->setMpExtraFee($quote->getMpExtraFee());

        $billingExtraFee  = $this->getMpExtraFee($order, DisplayArea::PAYMENT_METHOD);
        $shippingExtraFee = $this->getMpExtraFee($order, DisplayArea::SHIPPING_METHOD);
        $extraFee         = $this->getMpExtraFee($order, DisplayArea::CART_SUMMARY);

        if (!empty($billingExtraFee)) {
            $order->setHasBillingExtraFee(true);
        }
        if (!empty($shippingExtraFee)) {
            $order->setHasShippingExtraFee(true);
        }
        if (!empty($extraFee)) {
            $order->setHasExtraFee(true);
        }

        $totalsFees         = [];
        $extraFeeAmount     = 0;
        $baseExtraFeeAmount = 0;
        $extraFeeTotals     = $this->getMpExtraFee($order, DisplayArea::TOTAL);
        $extraFee           = $this->getMpExtraFee($quote);
        $orderQty           = $this->getTotalOrderedQty($order);
        foreach ($extraFeeTotals as $extraFeeTotal) {
            $extraFeeAmount           += $extraFeeTotal['value'];
            $baseExtraFeeAmount       += $extraFeeTotal['base_value'];
            $valueEachItem            = $extraFeeTotal['value'] / $orderQty;
            $baseValueEachItem        = $extraFeeTotal['base_value'] / $orderQty;
            $baseValueInclTaxEachItem = $extraFeeTotal['base_value_incl_tax'] / $orderQty;
            $valueExclTaxEachItem     = $extraFeeTotal['value_excl_tax'] / $orderQty;
            $valueInclTaxEachItem     = $extraFeeTotal['value_incl_tax'] / $orderQty;

            $ruleId = explode('_', $extraFeeTotal['code'])[4];
            /** @var Rule $rule */
            $rule = $this->ruleFactory->create()->load($ruleId);
            foreach ($order->getItems() as $item) {
                if ($this->isBundleOrConfig($item)) {
                    continue;
                }
                foreach ($extraFee as $Id => $option) {
                    if ($ruleId != $Id) {
                        continue;
                    }
                    if (is_array($option)) {
                        foreach ($option as $op) {
                            if (!str_contains($extraFeeTotal['code'], $op)) {
                                continue;
                            }
                            $options = $rule->getOptions() ? Data::jsonDecode($rule->getOptions())['option']['value'] : [];
                            if ((int) $options[$op]['type'] === 3) {
                                $valueEachItem            = $item->getOriginalPrice() * $options[$op]['amount'] / 100;
                                $baseValueEachItem        = $item->getBaseOriginalPrice() * $options[$op]['amount'] / 100;
                                $baseValueInclTaxEachItem = $item->getBasePriceInclTax() * $options[$op]['amount'] / 100;
                                $valueExclTaxEachItem     = $item->getOriginalPrice() * $options[$op]['amount'] / 100;
                                $valueInclTaxEachItem     = $item->getPriceInclTax() * $options[$op]['amount'] / 100;
                                if (!$item->getOriginalPrice() && $item->getparentItem()->getProductType() === 'configurable') {
                                    $parentItem               = $item->getparentItem();
                                    $valueEachItem            = $parentItem->getOriginalPrice() * $options[$op]['amount'] / 100;
                                    $baseValueEachItem        = $parentItem->getBaseOriginalPrice() * $options[$op]['amount'] / 100;
                                    $baseValueInclTaxEachItem = $parentItem->getBasePriceInclTax() * $options[$op]['amount'] / 100;
                                    $valueExclTaxEachItem     = $parentItem->getOriginalPrice() * $options[$op]['amount'] / 100;
                                    $valueInclTaxEachItem     = $parentItem->getPriceInclTax() * $options[$op]['amount'] / 100;
                                }
                            }
                        }
                    }
                }
                $totalsFees[$item->getProductId()][] = [
                    'code'                => $extraFeeTotal['code'],
                    'title'               => $extraFeeTotal['title'],
                    'label'               => $extraFeeTotal['label'],
                    'value'               => $valueEachItem,
                    'base_value'          => $baseValueEachItem,
                    'value_incl_tax'      => $baseValueInclTaxEachItem,
                    'value_excl_tax'      => $valueExclTaxEachItem,
                    'base_value_incl_tax' => $valueInclTaxEachItem,
                    'rf'                  => $extraFeeTotal['rf'],
                    'display_area'        => $extraFeeTotal['display_area'],
                    'apply_type'          => $extraFeeTotal['apply_type'],
                    'rule_label'          => $extraFeeTotal['rule_label']
                ];
            }
        }

        if ($quote instanceof Address) {
            $order->setGrandTotal($order->getGrandTotal() + $extraFeeAmount);
            $order->setBaseGrandTotal($order->getBaseGrandTotal() + $baseExtraFeeAmount);
        }

        if (!empty($totalsFees)) {
            foreach ($order->getItems() as $item) {
                if ($this->isBundleOrConfig($item)) {
                    continue;
                }
                if (isset($totalsFees[$item->getProductId()])) {
                    $item->setMpExtraFee(Data::jsonEncode($totalsFees[$item->getProductId()]));
                }
            }
        }
    }

    /**
     * @return Collection
     */
    public function getRuleCollection()
    {
        $storeId  = $this->getStoreId();
        $cacheKey = 'rule_collection_' . $storeId;

        if (isset(self::$staticRuleCache[$cacheKey])) {
            return self::$staticRuleCache[$cacheKey];
        }

        if (isset($this->ruleCollectionCache[$cacheKey])) {
            return $this->ruleCollectionCache[$cacheKey];
        }

        $currentTime          = $this->getCurrentTime();
        $currentTimeFormatted = $currentTime->format('Y-m-d H:i:s');

        $ruleCollection = $this->ruleCollectionFactory->create()
            ->addFieldToFilter('status', 1)
            ->addFieldToFilter('from_date', [
                ['lteq' => $currentTimeFormatted],
                ['null' => true]
            ])
            ->addFieldToFilter('to_date', [
                ['gteq' => $currentTimeFormatted],
                ['null' => true]
            ])
            ->setOrder('priority', 'ASC')
            ->setOrder('rule_id', 'ASC');

        // Validate available days properly
        $filteredCollection = $this->validateAvailableDays($ruleCollection);

        $this->ruleCollectionCache[$cacheKey] = $filteredCollection;
        self::$staticRuleCache[$cacheKey]     = $filteredCollection;

        return $filteredCollection;
    }

    /**
     * @param int|null $customerGroupId
     * @param int|null $storeId
     * @param string|null $area
     *
     * @return Collection
     */
    public function getFilteredRuleCollection($customerGroupId = null, $storeId = null, $area = null)
    {
        if ($customerGroupId === null) {
            $customerSession = $this->customerSession->create();
            $customerGroupId = $customerSession->isLoggedIn() ? $customerSession->getCustomerGroupId() : 0;
        }

        if ($storeId === null) {
            $storeId = $this->getStoreId();
        }

        $cacheKey = sprintf('filtered_rules_%d_%d_%s', $customerGroupId, $storeId, $area ?: 'all');

        if (isset($this->optimizedRuleCache[$cacheKey])) {
            return $this->optimizedRuleCache[$cacheKey];
        }

        $collection           = $this->ruleCollectionFactory->create();
        $currentTime          = $this->getCurrentTime();
        $currentTimeFormatted = $currentTime->format('Y-m-d H:i:s');
        $currentDay           = strtolower($currentTime->format('l'));

        $select = $collection->getSelect();

        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns('*');

        $select->where('status = 1');
        $select->where('from_date IS NULL OR from_date <= ?', $currentTimeFormatted);
        $select->where('to_date IS NULL OR to_date >= ?', $currentTimeFormatted);

        $select->where(
            'FIND_IN_SET(?, customer_groups)',
            $customerGroupId
        );

        $select->where(
            'FIND_IN_SET(?, store_ids) OR FIND_IN_SET("0", store_ids)',
            $storeId
        );

        if ($area !== null) {
            $select->where('area = ?', $area);
        }

        $configAvailableDays = $this->getConfigGeneral('available_days');
        $dayCondition        = 'available_days IS NULL OR available_days = "" OR FIND_IN_SET(?, available_days)';
        if ($configAvailableDays && strpos($configAvailableDays, $currentDay) !== false) {
            $dayCondition .= ' OR available_days = "mp-use-config"';
        }
        $select->where($dayCondition, $currentDay);

        $select->order(['priority ASC', 'rule_id ASC']);

        $this->optimizedRuleCache[$cacheKey] = $collection;

        return $collection;
    }

    /**
     * @param int|null $customerGroupId
     * @param int|null $storeId
     * @param string|null $area
     * @param mixed $quote
     *
     * @return array
     */
    public function getMinimalRuleData($customerGroupId = null, $storeId = null, $area = null, $quote = null)
    {
        if ($customerGroupId === null) {
            $customerSession = $this->customerSession->create();
            $customerGroupId = $customerSession->isLoggedIn() ? $customerSession->getCustomerGroupId() : 0;
        }

        if ($storeId === null) {
            $storeId = $this->getStoreId();
        }

        // Include quote state in cache key to ensure proper re-validation
        $quoteStateHash = '';
        if ($quote) {
            $quoteStateHash = hash('sha256', $this->serializer->serialize([
                'subtotal'    => $quote->getBaseSubtotal(),
                'grand_total' => $quote->getGrandTotal(),
                'items_count' => $quote->getItemsCount(),
                'items_qty'   => $quote->getItemsQty()
            ]));
        }

        $cacheKey = sprintf('minimal_rules_%d_%d_%s_%s', $customerGroupId, $storeId, $area ?: 'all', $quoteStateHash);

        if (isset($this->optimizedRuleCache[$cacheKey])) {
            return $this->optimizedRuleCache[$cacheKey];
        }

        $collection           = $this->ruleCollectionFactory->create();
        $currentTime          = $this->getCurrentTime();
        $currentTimeFormatted = $currentTime->format('Y-m-d H:i:s');
        $currentDay           = strtolower($currentTime->format('l'));

        // Select only essential fields
        $collection->getSelect()
            ->reset('columns')
            ->columns([
                'rule_id',
                'name',
                'priority',
                'apply_type',
                'area',
                'stop_further_processing'
            ]);

        // Apply all filters
        $select = $collection->getSelect();
        $select->where('status = 1');
        $select->where('from_date IS NULL OR from_date <= ?', $currentTimeFormatted);
        $select->where('to_date IS NULL OR to_date >= ?', $currentTimeFormatted);
        $select->where('FIND_IN_SET(?, customer_groups) OR FIND_IN_SET("0", customer_groups)', $customerGroupId);
        $select->where('FIND_IN_SET(?, store_ids) OR FIND_IN_SET("0", store_ids)', $storeId);

        if ($area !== null) {
            $select->where('area = ?', $area);
        }

        $configAvailableDays = $this->getConfigGeneral('available_days');
        $dayCondition        = 'available_days IS NULL OR available_days = "" OR FIND_IN_SET(?, available_days)';
        if ($configAvailableDays && strpos($configAvailableDays, $currentDay) !== false) {
            $dayCondition .= ' OR available_days = "mp-use-config"';
        }
        $select->where($dayCondition, $currentDay);

        $select->order(['priority ASC', 'rule_id ASC']);

        $result = [];
        foreach ($collection as $rule) {
            $fullRule           = $this->ruleFactory->create()->load($rule->getRuleId());
            $ruleCustomerGroups = $fullRule->getCustomerGroups();

            $isValid = true;

            if ($ruleCustomerGroups !== null && $ruleCustomerGroups !== '') {
                $customerGroupsArray = explode(',', $ruleCustomerGroups);
                $customerGroupValid  = in_array($customerGroupId, $customerGroupsArray);
                if (!$customerGroupValid) {
                    $isValid = false;
                }
            }

            if ($quote && $isValid) {
                $isValid = $this->validateRuleConditions($rule->getRuleId(), $quote);
            }

            if ($isValid) {
                $result[] = [
                    'rule_id'                 => $rule->getRuleId(),
                    'name'                    => $rule->getName(),
                    'priority'                => $rule->getPriority(),
                    'apply_type'              => $rule->getApplyType(),
                    'area'                    => $rule->getArea(),
                    'stop_further_processing' => $rule->getStopFurtherProcessing()
                ];
            }
        }

        $this->optimizedRuleCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Validate rule conditions against quote/address
     *
     * @param int $ruleId
     * @param mixed $quote
     *
     * @return bool
     */
    private function validateRuleConditions($ruleId, $quote)
    {
        try {
            $rule = $this->ruleFactory->create()->load($ruleId);

            if (!$rule->getId()) {
                return false;
            }

            // Get address for validation
            $address = $this->getAddressToValidate($quote);

            // Validate rule conditions
            return $rule->validate($address);

        } catch (\Exception $e) {
            // Log error and return false to be safe
            return false;
        }
    }

    /**
     * @param $ruleCollection
     *
     * @return Collection
     */
    public function validateAvailableDays($ruleCollection)
    {
        $currentTime         = $this->getCurrentTime();
        $currentDay          = strtolower($currentTime->format('l'));
        $configAvailableDays = $this->getConfigGeneral('available_days');

        $validRuleIds = [];

        foreach ($ruleCollection as $rule) {
            $availableDays = ($rule->getData('available_days') === 'mp-use-config')
                ? $configAvailableDays
                : $rule->getData('available_days');

            // If no available days are set, rule is always available
            if (!$availableDays || $availableDays === '') {
                $validRuleIds[] = $rule->getRuleId();
                continue;
            }

            // Check if current day is in available days
            if (strpos($availableDays, $currentDay) !== false) {
                $availableDaysArray = explode(',', $availableDays);
                if (in_array($currentDay, $availableDaysArray)) {
                    $validRuleIds[] = $rule->getRuleId();
                }
            }
        }

        // Create new collection with only valid rules
        if (!empty($validRuleIds)) {
            $filteredCollection = $this->ruleCollectionFactory->create()
                ->addFieldToFilter('rule_id', ['in' => $validRuleIds])
                ->addFieldToFilter('status', 1)
                ->setOrder('priority', 'ASC')
                ->setOrder('rule_id', 'ASC');
        } else {
            // Return empty collection if no valid rules
            $filteredCollection = $this->ruleCollectionFactory->create()
                ->addFieldToFilter('rule_id', ['in' => [0]]);
        }

        return $filteredCollection;
    }

    /**
     * Clear all caches
     */
    public function clearRuleCache()
    {
        $this->ruleCollectionCache = [];
        $this->filteredRuleCache   = [];
        $this->optimizedRuleCache  = [];
        self::$staticRuleCache     = [];
    }

    /**
     * Clear validation cache for a specific quote
     *
     * @param mixed $quote
     */
    public function clearValidationCacheForQuote($quote = null)
    {
        if (!$quote) {
            // Clear all validation cache if no quote specified
            foreach (array_keys($this->optimizedRuleCache) as $key) {
                if (strpos($key, 'minimal_rules_') === 0) {
                    unset($this->optimizedRuleCache[$key]);
                }
            }

            return;
        }

        // Clear cache entries for this specific quote
        $quoteId = $quote->getId() ?: 'temp';
        foreach (array_keys($this->optimizedRuleCache) as $key) {
            if (strpos($key, 'minimal_rules_') === 0 && strpos($key, (string) $quoteId) !== false) {
                unset($this->optimizedRuleCache[$key]);
            }
        }
    }

    /**
     * Get active attributes from mageplaza_extrafee_product_attribute table
     * Shared method to avoid code duplication
     *
     * @return array
     */
    public function getActiveAttributes()
    {
        // Return cached result if already fetched
        if ($this->activeAttributesCache !== null) {
            return $this->activeAttributesCache;
        }

        $connection = $this->resourceConnection->getConnection();

        // Sub select to get unique attribute_ids from extrafee table
        $subSelect = $connection->select();
        $subSelect->reset();
        $subSelect->from($this->resourceConnection->getTableName('mageplaza_extrafee_product_attribute'))
            ->group('attribute_id');

        // Join with eav_attribute to get attribute_code
        $select = $connection->select()->from(
            ['a' => $subSelect],
            new \Zend_Db_Expr('ea.attribute_code')
        )->joinInner(
            ['ea' => $this->resourceConnection->getTableName('eav_attribute')],
            'ea.attribute_id = a.attribute_id',
            []
        );

        $ensureAttributes = $connection->fetchAll($select);

        // Extract attribute codes from result
        $result = [];
        foreach ($ensureAttributes as $row) {
            if (isset($row['attribute_code'])) {
                $result[]['attribute_code'] = $row['attribute_code'];
            }
        }

        // Cache the result
        $this->activeAttributesCache = $result;

        return $result;
    }

    /**
     * @param $storeId
     *
     * @return string
     */
    public function getCurrentTime($storeId = null)
    {
        if ($storeId === null) {
            $storeId = $this->getStoreId();
        }

        $cacheKey = 'current_time_' . $storeId;

        // Check if cached time is still valid (cache for 1 minute)
        if (isset($this->currentTimeCache[$cacheKey]) &&
            time() - $this->currentTimeCache[$cacheKey]['timestamp'] < 60) {
            return $this->currentTimeCache[$cacheKey]['datetime'];
        }

        $timeZoneString = $this->scopeConfig->getValue(
            'general/locale/timezone',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $timezone    = new DateTimeZone($timeZoneString);
        $currentTime = new DateTime('now', $timezone);

        // Cache the result
        $this->currentTimeCache[$cacheKey] = [
            'datetime'  => $currentTime,
            'timestamp' => time()
        ];

        return $currentTime;
    }

    /**
     * @param $collection
     * @param $ruleId
     * @param $type
     *
     * @return int|mixed
     */
    public function getReportTotal($collection, $ruleId, $type)
    {
        $totals = 0;
        foreach ($collection as $element) {
            foreach ($element->getItems() as $item) {
                if ($item->getMpExtraFee()) {
                    $extraFee = $this->unserialize($item->getMpExtraFee());
                    if (count($extraFee) == 0) {
                        continue;
                    }
                    foreach ($extraFee as $total) {
                        $id = array_filter(preg_split("/\D+/", $total['code']));
                        $id = reset($id);
                        if ($id == $ruleId) {
                            if ($type == 'invoice') {
                                $totals += $total['base_value'] * $item->getQty();
                            }
                            if ($type == 'creditmemo' && $total['rf'] == 1) {
                                $totals += $total['base_value'] * $item->getQty();
                            }
                        }
                    }
                }
            }
        }

        return $totals;
    }

    /**
     * @param $quote
     */
    public function setExtraFeeNote($quote)
    {
        $extraFee     = $this::jsonDecode($quote->getMpExtraFee());
        $extraFeeNote = $this->checkoutSession->getExtraFeeMultiNote() ?: [];

        if (count($extraFeeNote) && $quote->getAddressId()) {
            $addressId = $quote->getAddressId();
            if (isset($extraFeeNote[$addressId])) {
                $extraFee['note'] = $extraFeeNote[$addressId];
                $quote->setMpExtraFee($this::jsonEncode($extraFee));
                $quote->save();
                unset($extraFeeNote[$addressId]);
            }
        }

        if (!count($extraFeeNote)) {
            $this->checkoutSession->unsExtraFeeMultiNote();
        }
    }

    /**
     * @param QuoteModel|Order|Address|Address\Total $quote
     * @param bool $area
     *
     * @return array
     */
    public function getMpExtraFee($quote, $area = false)
    {
        $extraFee = $quote->getMpExtraFee() ? $this::jsonDecode($quote->getMpExtraFee()) : [];
        switch ($area) {
            case DisplayArea::PAYMENT_METHOD:
                if (isset($extraFee['payment'])) {
                    parse_str($extraFee['payment'], $result);

                    return $result;
                }

                return [];
            case DisplayArea::SHIPPING_METHOD:
                if (isset($extraFee['shipping'])) {
                    parse_str($extraFee['shipping'], $result);

                    return $result;
                }

                return [];
            case DisplayArea::CART_SUMMARY:
                if (isset($extraFee['summary'])) {
                    parse_str($extraFee['summary'], $result);

                    return $result;
                }

                return [];
            case DisplayArea::TOTAL:
                if (isset($extraFee['totals'])) {
                    return $extraFee['totals'];
                }

                return [];
            default:
                $result = [];
                foreach ($extraFee as $index => $item) {
                    if (in_array($index, ['totals', 'is_invoiced', 'is_refunded', 'note'])) {
                        continue;
                    }
                    parse_str($item, $rule);
                    if (isset($rule['rule']) && is_array($rule['rule'])) {
                        foreach ($rule['rule'] as $key => $value) {
                            $result[$key] = $value;
                        }
                    }
                }

                return $result;
        }
    }

    /**
     * @param Rule $rule
     *
     * @return false|int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function checkCustomerGroup($rule)
    {
        $customerGroups  = explode(',', $rule->getCustomerGroups());
        $customerSession = $this->customerSession->create();
        $customerGroupId = $customerSession->isLoggedIn() ? $customerSession->getCustomerGroupId() : 0;

        if (in_array($customerGroupId, $customerGroups)) {
            return true;
        }

        return false;
    }

    /**
     * @param Rule $rule
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function checkStoreIds($rule)
    {
        $storeIds     = explode(',', $rule->getStoreIds());
        $currentStore = $this->getStoreId();
        if (in_array(0, $storeIds)) {
            return true;
        } else {
            if (in_array($currentStore, $storeIds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $moduleName
     *
     * @return bool
     */
    public function moduleIsEnable($moduleName)
    {
        if ($this->_moduleManager->isEnabled($moduleName)) {
            return true;
        }

        return false;
    }

    /**
     * @param $data
     */
    protected function setNoteToSession($data)
    {
        $checkoutSession = $this->getCheckoutSession();
        $extraFeeNote    = $checkoutSession->getExtraFeeNote() ?: [];
        $params          = [];
        parse_str($data, $params);

        foreach ($params as $key => $value) {
            if (str_contains($key, 'mp-extrafee-note') && !empty($value)) {
                $extraFeeNote[$key] = $value;
            }
        }

        $checkoutSession->setExtraFeeNote($extraFeeNote);
    }

    /**
     * @param $extraFeeNote
     * @param $data
     *
     * @return string
     */
    protected function setNoteToParams($extraFeeNote, $data)
    {
        $data = explode('&', $data);

        foreach ($extraFeeNote as $key => $value) {
            foreach ($data as $index => $param) {
                if (str_contains($param, $key)) {
                    $data[$index] = $key . '=' . $value;
                }
            }
        }
        $data = implode('&', $data);

        return $data;
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     *
     * @return Address
     */
    public function getAddressToValidate($quote)
    {
        if (!$this->addressToValidate
            || ($this->addressToValidate && !$this->addressToValidate->getData('total_qty'))
        ) {
            $this->addressToValidate =
                clone($quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress());
        }

        $defaultCountryId = $this->getDefaultCountryId();
        $defaultRegionId  = $this->getDefaultRegionId();
        $defaultPostcode  = $this->getDefaultPostcode();

        if (!$this->addressToValidate->getCountryId()) {
            $this->addressToValidate->setCountryId($defaultCountryId);
        }

        if (!$this->addressToValidate->getRegionId()) {
            $this->addressToValidate->setRegionId($defaultRegionId);
        }

        if (!$this->addressToValidate->getPostcode()) {
            $this->addressToValidate->setPostcode($defaultPostcode);
        }

        if ($quote->getPayment() && $quote->getPayment()->getMethod()) {
            $this->addressToValidate->setPaymentMethod($quote->getPayment()->getMethod());
        }

        return $this->addressToValidate;
    }

    /**
     * Get Current Store ID
     *
     * @return int
     */
    public function getStoreId()
    {
        try {
            return $this->storeManager->getStore()->getId();
        } catch (NoSuchEntityException $e) {
            return 0;
        }
    }
}
