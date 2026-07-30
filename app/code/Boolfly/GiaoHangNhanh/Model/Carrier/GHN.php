<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   Giao hang nhanh
 */

namespace Boolfly\GiaoHangNhanh\Model\Carrier;

use Boolfly\GiaoHangNhanh\Api\Data\TrackInterface;
use Boolfly\GiaoHangNhanh\Helper\Data as GHNHelperData;
use Boolfly\GiaoHangNhanh\Helper\Rate;
use Boolfly\GiaoHangNhanh\Model\Config;
use Boolfly\GiaoHangNhanh\Model\Data\TrackData;
use Boolfly\GiaoHangNhanh\Model\ResourceModel\TrackModel\TrackCollection;
use Boolfly\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Boolfly\GiaoHangNhanh\Model\Service\Request\AbstractDataBuilder;
use Boolfly\IntegrationBase\Model\Service\Command\CommandException;
use Boolfly\IntegrationBase\Model\Service\Command\CommandPoolInterface;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingResultFactory;
use Psr\Log\LoggerInterface;

/**
 * Class GHN
 *
 * @package Boolfly\GiaoHangNhanh\Model\Carrier
 */
abstract class GHN extends AbstractCarrier implements CarrierInterface
{
    const SERVICE_NAME = 'GHN';
    const EMPTY = 'N/A';

    /**
     * @var string
     */
    protected $_code = 'giaohangnhanh';

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var MethodFactory
     */
    private $rateMethodFactory;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Rate
     */
    private $helperRate;

    /**
     * @var array
     */
    protected $availableServices = [];

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @var GHNHelperData
     */
    protected GHNHelperData $ghnHelperData;

    /**
     * Rate result data
     *
     * @var Result|null
     */
    protected $_result;

    /**
     * GHN constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param Config $config
     * @param Rate $helperRate
     * @param CommandPoolInterface $commandPool
     * @param GHNHelperData $ghnHelperData
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface            $scopeConfig,
        ErrorFactory                    $rateErrorFactory,
        LoggerInterface                 $logger,
        ResultFactory                   $rateResultFactory,
        MethodFactory                   $rateMethodFactory,
        Config                          $config,
        Rate                            $helperRate,
        CommandPoolInterface            $commandPool,
        GHNHelperData                   $ghnHelperData,
        protected TrackingResultFactory $trackFactory,
        protected StatusFactory         $trackStatusFactory,
        protected TrackCollection       $trackCollection,
        array                           $data = []
    )
    {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->config = $config;
        $this->helperRate = $helperRate;
        $this->commandPool = $commandPool;
        $this->ghnHelperData = $ghnHelperData;
    }

    /**
     * @inheritDoc
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag(Config::IS_ACTIVE)) {
            return false;
        }

//        $shippingAddress = $request->getShippingAddress();
//        if (is_null($shippingAddress->getCountryId()) ||
//            strtoupper($shippingAddress->getCountryId()) !== "VN") {
//            return false;
//        }

        if (!is_null($shippingCost = $this->estimateShippingCost($request))) {
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
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData(Config::NAME)];
    }

    /**
     * @param RateRequest $request
     * @return string|null
     */
    protected function estimateShippingCost(RateRequest $request)
    {
        $shippingAddress = $request->getShippingAddress();
        $shippingFee = 10;

        try {
            $this->prepareServices($request);
            if ($serviceItem = $this->getAvailableService()) {
                $serviceId = $serviceItem[AbstractDataBuilder::SERVICE_ID];
                $serviceTypeId = $serviceItem[AbstractDataBuilder::SERVICE_TYPE_ID];
                $shippingAddress->setData('shipping_service_id', $serviceId);
                $shippingAddress->setData('shipping_service_type_id', $serviceTypeId);

                $subject = [
                    'rate_request' => $request,
                    'service_id' => $serviceId,
                ];

                // Has the order with payment COD?
                $isOrderPaymentCod = $this->ghnHelperData->isOrderPaymentCod();
                if ($isOrderPaymentCod) {
                    $totalOrder = $this->ghnHelperData->getTotalOrder();
                    $totalOrder = $this->helperRate->getVndAmountByStoreCurrency($totalOrder);
                    $subject['total_order'] = $totalOrder;
                }

                $commandResult = $this->commandPool->get('calculate_rate')->execute($subject);
                $rate = SubjectReader::readRate($commandResult->get());
                $shippingFee = SubjectReader::readServiceFee($rate);
            }
            return $this->helperRate->convertPriceToDefaultCurrency($shippingFee);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param RateRequest $request
     * @throws CommandException
     * @throws NotFoundException
     */
    protected function prepareServices($request)
    {
        $this->availableServices = SubjectReader::readServices(
            $this->commandPool->get('get_services')->execute(['rate_request' => $request])->get()
        );
    }

    /**
     * @return mixed|null
     */
    protected function getAvailableService()
    {
        if (count($this->availableServices)) {
            foreach ($this->availableServices as $serviceItem) {
                $isMatchName = is_array($serviceItem) && SubjectReader::readServiceName($serviceItem) == static::SERVICE_NAME;
                $isMatchTypeId = is_array($serviceItem) && defined('static::SERVICE_TYPE_ID') && isset($serviceItem['service_type_id']) && $serviceItem['service_type_id'] == static::SERVICE_TYPE_ID;
                if ($isMatchName || $isMatchTypeId) {
                    return $serviceItem;
                }
            }
        }

        return null;
    }

    /**
     * @param RateRequest $request
     * @return bool
     */
    abstract public function canDisplay(RateRequest $request): bool;

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
     * @param array|string $trackings
     * @return void
     */
    protected function getAllTracking(array|string $trackings)
    {
        if (is_array($trackings)) {
            $trackings = $trackings[count($trackings) - 1];
        }
        $trackCollection = $this->trackCollection->addFieldToFilter(TrackInterface::TRACKING_CODE, $trackings)->getItems();
        $result = $this->trackFactory->create();
        $tracking = $this->trackStatusFactory->create();
        $tracking->setCarrier($this->_code);
        $tracking->setCarrierTitle($this->getConfigData('title'));
        $tracking->setTracking($trackings);
        $progressdetail = [];
        foreach ($trackCollection as $track) {
            /** @var $track TrackData */
            $tracking->addData($track->getData());
            $additionalData = json_decode($track->getAdditionalData(), true);
            $deliveryDate = $additionalData['deliverydate'];
            $deliveryTime = $additionalData['deliverytime'];
            if ($track->getResultCode() == TrackInterface::RESULT_CODE_SUCCESS) {
                $progressdetail[] = [
                    'activity' => __($track->getStatusLabel() ?? $track->getStatusCode()),
                    'deliverydate' => $deliveryDate ?? self::EMPTY,
                    'deliverytime' => $deliveryTime ?? self::EMPTY,
                    'deliverylocation' => $track->getWarehouse() ?? self::EMPTY
                ];
            }
        }
        $tracking->addData([
            'progressdetail' => $progressdetail
        ]);
        $result->append($tracking);
        $this->_result = $result;
    }


    /**
     * Get maximum weight and convert from gram to kilogram
     *
     * @return int
     */
    protected function getMaxWeight(): float
    {
        return (float)$this->getAdvancedConfig('maximum_weight')/1000;
    }

    /**
     * @return int
     */
    protected function getMaxWidth(): float
    {
        return (float)$this->getAdvancedConfig('maximum_width');
    }

    /**
     * @return int
     */
    protected function getMaxHeight(): float
    {
        return (float)$this->getAdvancedConfig('maximum_height');
    }

    /**
     * @return int
     */
    protected function getMaxLength(): float
    {
        return (float)$this->getAdvancedConfig('maximum_length');
    }

    /**
     * @param $field
     * @return bool
     */
    public function getAdvancedConfig($field)
    {
        if (empty($this->_code)) {
            return false;
        }
        $path = 'carriers/ghn/advanced_settings/' . $this->_code . '/' . $field;
        return $this->_scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );
    }

    /**
     * @return int
     */
    protected function getMaxLengthItem()
    {
        return (int)$this->getAdvancedConfig('maximum_length_item');
    }

    /**
     * @return int
     */
    protected function getMaxWidthItem(): int
    {
        return (int)$this->getAdvancedConfig('maximum_width_item');
    }

    /**
     * @return int
     */
    protected function getMaxHeightItem(): int
    {
        return (int)$this->getAdvancedConfig('maximum_height_item');
    }

    /**
     * Get max weight of the item and convert from gram to kilogram
     * @return int
     */
    protected function getMaxWeightItem(): int
    {
        return (int)$this->getAdvancedConfig('maximum_weight_item')/1000;
    }

     /**
     * Get max converted mass of the item and convert from gram to kilogram
     * @return int
     */
    protected function getMaxConvertedMassItem(): int
    {
        return (int)$this->getAdvancedConfig('maximum_converted_mass_item')/1000;
    }

     /**
     * Get max converted mass of the order and convert it from gram to kilogram
     * @return int
     */
    protected function getMaxConvertedMassOrder(): int
    {
        return (int)$this->getAdvancedConfig('maximum_converted_mass_order')/1000;
    }
}
