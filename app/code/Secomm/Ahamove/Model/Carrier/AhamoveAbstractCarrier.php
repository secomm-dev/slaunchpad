<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Carrier;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Config\CacheInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingResultFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Secomm\Ahamove\Api\Data\AhamoveAddressInterface;
use Secomm\Ahamove\Command\CreateShipment;
use Secomm\Ahamove\Helper\Data as AhamoveHelper;
use Secomm\Ahamove\Logger\Logger as LoggerShipping;
use Secomm\Ahamove\Model\AhamoveOrderStatus;
use Secomm\Ahamove\Model\Config;
use Secomm\Ahamove\Model\Connect\Api;
use Secomm\Ahamove\Model\Data\AhamoveAddressFactory;
use Secomm\Ahamove\Model\Data\PackageItemFactory;
use Secomm\Ahamove\Model\ErrorMessageManager;
use Secomm\Ahamove\Model\PackageFactory;
use Secomm\Ahamove\Model\ResourceModel\AhamoveOrderStatus\CollectionFactory;

abstract class AhamoveAbstractCarrier extends AbstractCarrier implements CarrierInterface
{
    const SERVICE_IDS = [
        [
            "_id" => Config::SERVICE_ID_POOL
        ],
        [
            "_id" => Config::SERVICE_ID_VAN
        ],
        [
            "_id" => Config::SERVICE_ID_FOUR_HOURS
        ],
        [
            "_id" => Config::SERVICE_ID_BIKE
        ],
    ];

    const ADVANCED_SETTINGS = [
        'active',
        'name',
        'title',
        'sort_order',
        'showmethod',
        'operation_day',
        'hours_of_operation_from',
        'hours_of_operation_to',
        'specificerrmsg',
        'maximum_weight',
        'maximum_width',
        'maximum_height',
        'maximum_length',
    ];

    /**
     * @var string
     */
    protected $serviceId = '';

    /**
     * Rate result data
     *
     * @var Result|null
     */
    protected $_result;
    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;
    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;

    protected $shippingTablerates;

    /**
     * GHN constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param AhamoveAddressFactory $ahamoveAddressFactory
     * @param Api $api
     * @param AhamoveHelper $ahamoveHelper
     * @param CountryFactory $countryFactory
     * @param RegionFactory $regionFactory
     * @param LoggerShipping $loggerShipping
     * @param SerializerInterface $serializer
     * @param CacheInterface $cache
     * @param TimezoneInterface $timezone
     * @param TrackingResultFactory $trackFactory
     * @param StatusFactory $trackStatusFactory
     * @param CheckoutSession $checkoutSession
     * @param ErrorMessageManager $errorMessageManager
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface            $scopeConfig,
        ErrorFactory                    $rateErrorFactory,
        LoggerInterface                 $logger,
        ResultFactory                   $rateResultFactory,
        MethodFactory                   $rateMethodFactory,
        protected AhamoveAddressFactory $ahamoveAddressFactory,
        protected Api                   $api,
        protected AhamoveHelper         $ahamoveHelper,
        protected CountryFactory        $countryFactory,
        protected RegionFactory         $regionFactory,
        protected LoggerShipping        $loggerShipping,
        protected SerializerInterface   $serializer,
        protected CacheInterface        $cache,
        protected TimezoneInterface     $timezone,
        protected TrackingResultFactory $trackFactory,
        protected StatusFactory         $trackStatusFactory,
        protected CheckoutSession       $checkoutSession,
        protected ErrorMessageManager   $errorMessageManager,
        protected PackageItemFactory    $packageItemFactory,
        protected PackageFactory        $packageFactory,
        protected RequestInterface      $request,
        protected OrderRepository       $orderRepository,
        protected QuoteFactory          $quote,
        protected CreateShipment        $createShipment,
        protected CollectionFactory     $ahamoveOrderStatusCollectionFactory,
        protected AddressFactory        $quoteAddressFactory,
        array                           $shippingTablerates = [],
        array                           $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->shippingTablerates = $shippingTablerates;
    }

    /**
     * @param RateRequest $request
     * @return Result | bool | Error
     * @throws Exception
     */
    public function collectRates(RateRequest $request)
    {
        try {
            if (!$this->getConfigFlag(Config::IS_ACTIVE)) {
                return false;
            }

            $errorMsg = $this->getConfigData('specificerrmsg');
            if (!$this->canDisplay($request)) {
                if ($this->getConfigData('showmethod')) {
                    $error = $this->_rateErrorFactory->create();
                    $error->setCarrier($this->_code);
                    $error->setCarrierTitle($this->getConfigData('title') . " " . $this->getConfigData(Config::NAME));
                    $error->setErrorMessage(
                        $errorMsg != '' ? $errorMsg : __(
                            'Sorry, but we can\'t deliver to the destination country with this shipping module.'
                        )
                    );
                    return $error;
                } else {
                    return false;
                }
            }

            // estimation fee based on shipping tablerate
            $resultShippingMethod = $this->getResultShippingTablerates($request);
            if ($resultShippingMethod instanceof Result) {
                return $resultShippingMethod;
            }

            if ($shippingCost = $this->estimateShippingCost($request)) {
                /** @var Result $result */
                $result = $this->rateResultFactory->create();
                /** @var Method $method */
                $method = $this->rateMethodFactory->create();
                $method->setCarrier($this->_code);
                $method->setCarrierTitle($this->getConfigData(Config::TITLE));
                $method->setMethod($this->_code);
                $method->setMethodTitle($this->getConfigData(Config::NAME));
                $method->setPrice($shippingCost);
                $method->setCost($shippingCost);

                $result->append($method);

                return $result;
            } else {
                if ($this->getConfigData('showmethod')) {
                    $error = $this->_rateErrorFactory->create();
                    $error->setCarrier($this->_code);
                    $error->setCarrierTitle($this->getConfigData('title') . " " . $this->getConfigData(Config::NAME));
                    $error->setErrorMessage(__($errorMsg));
                    return $error;
                }
                return false;
            }
        } catch (Exception $exception) {
            $this->loggerShipping->error($exception->getMessage());
        }
    }

    /**
     * Retrieve config flag for store by field
     *
     * @param string $field
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getConfigFlag($field)
    {
        if (empty($this->_code)) {
            return false;
        }
        if (in_array($field, self::ADVANCED_SETTINGS)) {
            $path = 'carriers/ahamove/advanced_settings/' . $this->_code . '/' . $field;
        } else {
            $path = 'carriers/' . $this->_code . '/' . $field;
        }

        return $this->_scopeConfig->isSetFlag(
            $path,
            ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );
    }

    /**
     * Retrieve information from carrier configuration
     *
     * @param string $field
     * @return  false|string
     */
    public function getConfigData($field, $code = '')
    {
        if (empty($this->_code)) {
            if ($code == '') {
                return false;
            } else {
                $this->_code = $code;
            }
        }
        if (in_array($field, self::ADVANCED_SETTINGS)) {
            $path = 'carriers/ahamove/advanced_settings/' . $this->_code . '/' . $field;
        } else {
            $path = 'carriers/ahamove/' . $field;
        }
        return $this->_scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );
    }

    /**
     * @param string $message
     * @return void
     */
    protected function setErrorMessage(string $message): void
    {
        $this->errorMessageManager->setErrorMessage($message);
    }

    /**
     * @param RateRequest $request
     * @return float
     * @throws Exception
     */
    protected function estimateShippingCost(RateRequest $request): float
    {
        try {
            $ahamoveAddressFactory = $this->ahamoveAddressFactory->create();
            $region = $this->getRegion($request);
            $ahamoveAddressFactory->setRegionCodeTo((string)$region);
            $regionName = '';
            $regionCode = $request->getDestRegionCode();
            if ($regionCode) {
                $regionModel = $this->regionFactory->create()->loadByCode($regionCode, $request->getDestCountryId());
                $regionName = $regionModel->getName() ?: $region;
            }
            $ahamoveAddressFactory->setCityTo((string)$this->getCityTo($request))
                ->setRegionCodeTo((string)($regionName ?: $region))
                ->setStreetTo((string)$this->getFullStreet($request))
                ->setPostCodeTo((string)$request->getDestPostcode())
                ->setCountryIdTo((string)$this->ahamoveHelper->getShippingCountryNameByCode($request->getDestCountryId()))
                ->setNameTo('')
                ->setRemark('')
                ->setPhoneTo('');
            $ahamoveAddressFactory->setCityFrom($this->ahamoveHelper->getCity())
                ->setRegionCodeFrom((string)$this->ahamoveHelper->getShippingRegion())
                ->setStreetFrom((string)$this->ahamoveHelper->getShippingStreet())
                ->setPostCodeFrom((string)$this->ahamoveHelper->getShippingPostcode())
                ->setCountryIdFrom((string)$this->ahamoveHelper->getShippingCountryName())
                ->setNameFrom('')
                ->setPhoneFrom('');
            return $this->calculateShippingFee($ahamoveAddressFactory);
        } catch (Exception $exception) {
            return 0;
        }
    }

    /**
     * @param AhamoveAddressInterface $ahamoveAddress
     * @return float
     * @throws Exception
     */
    abstract public function calculateShippingFee(AhamoveAddressInterface $ahamoveAddress): float;

    /**
     * @param array $params
     * @return array|bool
     */
    protected function loadFromCache(array $params): array|bool
    {
        $storeId = (string)$this->getStore();
        $cacheKey = $this->ahamoveHelper->generateCacheKey($storeId . '_' . $this->serializer->serialize($params));
        $cacheData = $this->cache->load($cacheKey);
        if ($cacheData) {
            $result = $this->serializer->unserialize($cacheData);
            if (is_array($result)) {
                return $result;
            } else {
                return false;
            }
        } else {
            return [];
        }
    }

    protected function getServiceId(): mixed
    {
        return $this->serviceId;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData(Config::NAME)];
    }

    /**
     * Validate request for available ship countries.
     *
     * @param DataObject $request
     * @return $this|bool|false|AbstractModel
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function checkAvailableShipCountries(DataObject $request)
    {
        $speCountriesAllow = [Config::AVAIABLECOUNTRY];
        $showMethod = $this->getConfigData('showmethod');
        /*
         * for specific countries, the flag will be 1
         */
        if (in_array($request->getDestCountryId(), $speCountriesAllow)) {
            return $this;
        } elseif ($showMethod) {
            /** @var Error $error */
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $errorMsg = $this->getConfigData('specificerrmsg');
            $error->setErrorMessage(
                $errorMsg ? $errorMsg : __(
                    'Sorry, but we can\'t deliver to the destination country with this shipping module.'
                )
            );

            return $error;
        } else {
            return false;
        }
    }

    /**
     * Get current quote id
     *
     * @return int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getCurrentQuoteId(): ?int
    {
        $quote = $this->getQuote();
        if (is_null($quote)) {
            return null;
        }
        return $this->getQuote()->getEntityId();
    }

    /**
     * Get current shipping method
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getShippingMethod(): string
    {
        $quote = $this->getQuote();
        if (is_null($quote)) {
            return '';
        }
        $shippingMethod = $quote->getShippingAddress()->getShippingMethod();
        if (is_null($shippingMethod)) {
            return '';
        }
        $length = strlen($shippingMethod);
        $halfLength = floor($length / 2);
        $shippingMethod = substr($shippingMethod, 0, $halfLength);
        return $shippingMethod;
    }

    /**
     * Get maximum weight and convert from gram to kilogram
     *
     * @return int
     */
    protected function getMaxWeight(): int
    {
        return (int)$this->getConfigData('maximum_weight') / 1000;
    }

    /**
     * @return int
     */
    protected function getMaxWidth(): int
    {
        return (int)$this->getConfigData('maximum_width');
    }

    /**
     * @return int
     */
    protected function getMaxHeight(): int
    {
        return (int)$this->getConfigData('maximum_height');
    }

    /**
     * @return int
     */
    protected function getMaxLength(): int
    {
        return (int)$this->getConfigData('maximum_length');
    }

    /**
     * Get tracking information
     *
     * @param string $tracking
     * @return string|false
     */
    public function getTrackingInfo($tracking)
    {
        $result = $this->getTracking($tracking);

        if ($result instanceof \Magento\Shipping\Model\Tracking\Result) {
            $trackings = $result->getAllTrackings();
            if ($trackings) {
                return $trackings[0];
            }
        } elseif (is_string($result) && !empty($result)) {
            return $result;
        }

        return false;
    }

    /**
     * Get tracking
     *
     * @param string|string[] $trackings
     * @return Result
     */
    public function getTracking($trackings)
    {
        if (!is_array($trackings)) {
            $trackings = [$trackings];
        }
        $this->getAllTracking($trackings);

        return $this->_result;
    }

    /**
     * @param RateRequest $request
     * @return mixed|string
     */
    private function getRegion(RateRequest $request)
    {
        try {
            if ($request->getDestRegion() != '') {
                return $request->getDestRegion();
            }
            if ($request->getDestRegionCode() != '') {
                return $request->getDestRegionCode();
            }
            $region = $request->getData('shipping_address')->getData('region');
            if (is_array($region)) {
                return $region['region'];
            } else {
                return !is_null($this->getShippingAddressSelected($request)) ? $this->getShippingAddressSelected($request)->getRegion() : '';
            }
        } catch (Exception $exception) {
            return '';
        }
    }

    /**
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getShippingAddressSelected($request): mixed
    {
        try {
            $shippingAddressId = $request->getData('shipping_address')->getAddressId();
            $address = $this->quoteAddressFactory->create()->load($shippingAddressId);
            if ($address) {
                return $address;
            }
            return null;
        } catch (\Exception $exception) {
            return null;
        }
    }

    public function getFullStreet(RateRequest $request): mixed
    {
        if ($request->getDestStreet() != '') {
            return $request->getDestStreet();
        } else {
            $street = $request->getData('shipping_address')->getData('street');
            if (empty($street)) {
                $street = !is_null($this->getShippingAddressSelected($request)) ? $this->getShippingAddressSelected($request)->getStreet() : '';
            }
            return is_array($street) ? implode("\n", $street) : ($street ?? '');
        }
    }

    /**
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getCityTo(RateRequest $request): mixed
    {
        if ($request->getDestCity() != '') {
            return $request->getDestCity();
        }
        $region = $request->getData('shipping_address')->getData('city');
        if (is_array($region)) {
            return $region['city'];
        } else {
            return !is_null($this->getShippingAddressSelected($request)) ? $this->getShippingAddressSelected($request)->getCity() : '';
        }
    }

    /**
     * Parse xml tracking response
     *
     * @param string[] $trackings
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function getAllTracking(array $trackings): void
    {
        $resultArr = [];
        $awbinfoData = [];
        $packageProgress = [];
        foreach ($trackings as $itemTracking) {
            $ahamoveEventModels = $this->ahamoveOrderStatusCollectionFactory->create();
            $ahamoveEventModels->addFieldToFilter('order_ahamove_id', ['eq' => $itemTracking]);
            $ahamoveEventModels->setOrder('entity_id', 'DESC');

            foreach ($ahamoveEventModels->getItems() as $ahamoveEventModelItem) {
                $shipmentEventArray = [];
                $failComment = null;
                $subStatus = null;
                $orderData = json_decode($ahamoveEventModelItem->getOrderData(), true);
                $activityMgs = __($this->ahamoveHelper->getStatusLabel($ahamoveEventModelItem->getStatus()));
                $path = isset($orderData['path'][0]['address']) ? $orderData['path'][0]['address'] : '';
                if ($ahamoveEventModelItem->getStatus() == AhamoveOrderStatus::STATE_COMPLETED) {
                    if (isset($orderData['path'][1]['status']) && $orderData['path'][1]['status'] == AhamoveOrderStatus::SUB_STATUS_FAILED) {
                        $failTime = $orderData['path'][1]['fail_time'];
                        $failComment = $orderData['path'][1]['fail_comment'];
                    }
                    if (isset($orderData['sub_status']) && $orderData['sub_status'] == AhamoveOrderStatus::SUB_STATUS_IN_RETURN) {
                        $subStatus = $orderData['sub_status'];
                    }
                    if (isset($orderData['sub_status']) && $orderData['sub_status'] == AhamoveOrderStatus::SUB_STATUS_RETURNED) {
                        $subStatus = $orderData['sub_status'];
                    }
                    if (isset($failComment) && isset($subStatus)) {
                        $activityMgs = $activityMgs . '(' . __((string)$failComment) . ')';
                    } else {
                        $path = isset($orderData['path'][1]['address']) ? $orderData['path'][1]['address'] : '';
                    }
                }
                if ($ahamoveEventModelItem->getStatus() == AhamoveOrderStatus::STATE_CANCELLED) {
                    if (isset($orderData['cancel_comment'])) {
                        $failTime = $orderData['cancel_time'];
                        $failComment = $orderData['cancel_comment'];
                    }
                    if (isset($failComment)) {
                        $activityMgs = $activityMgs . '(' . __((string)$failComment) . ')';
                    }
                }

                $shipmentEventArray['activity'] = $ahamoveEventModelItem->getStatus() . (isset($subStatus) ? ' - ' . __($subStatus) : '') . '-' . $activityMgs;
                $shipmentEventArray['deliverydate'] = (string)explode(' ', $ahamoveEventModelItem->getCreatedAt())[0];
                $shipmentEventArray['deliverytime'] = (string)explode(' ', $ahamoveEventModelItem->getCreatedAt())[1];
                $shipmentEventArray['deliverylocation'] = $path;
                $packageProgress[] = $shipmentEventArray;
            }
            $awbinfoData['progressdetail'] = $packageProgress;
            if (isset($ahamoveEventModelItem)) {
                $awbinfoData['url'] = $ahamoveEventModelItem->getSharedLink() ?? '';
            }
            $resultArr[$itemTracking] = $awbinfoData;
        }

        $result = $this->trackFactory->create();

        if (!empty($resultArr)) {
            foreach ($resultArr as $trackNum => $data) {
                $tracking = $this->trackStatusFactory->create();
                $tracking->setCarrier($this->_code);
                $tracking->setCarrierTitle($this->getConfigData('title'));
                $tracking->setTracking($trackNum);
                $tracking->addData($data);
                $result->append($tracking);
            }
        }

        $this->_result = $result;
    }
}
