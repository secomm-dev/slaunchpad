<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Carrier\ShippingMethod;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Secomm\Ahamove\Api\Data\AhamoveAddressInterface;
use Secomm\Ahamove\Model\Config;
use Secomm\Ahamove\Model\Data\PackageItem;
use Secomm\Ahamove\Model\Package;

abstract class AhamoveShippingMethod extends \Secomm\Ahamove\Model\Carrier\AhamoveAbstractCarrier implements \Secomm\PackagingManager\Api\PackagingServiceInterface
{
    /**
     * Key is Magento service id, value is Ahamove service id
     * @var string
     */
    const GROUP_STANDARD = [
        'bike' => 'BIKE',
        'van' => 'VAN-500'
    ];

    const GROUP_EXPRESS = [
        'two_hours' => '2H-PUBLIC',
    ];

    const CONFIG_CONVENTIONAL_NUMBER = 'ahamove/general/conventional_number';
    const DEFAULT_CONVENTIONAL_NUMBER = 3000;

    protected string $cityID = '';
    /**
     * This service will be calculated shipping fee
     *
     * @var array
     */
    protected array $arrayStandardServiceValid = [];

    /**
     * This service will be calculated shipping fee
     *
     * @var array
     */
    protected array $arrayExpressServiceValid = [];

    protected PackageItem $packageItem;
    protected Package $package;

    /**
     * @param AhamoveAddressInterface $ahamoveAddress
     * @return float
     * @throws NoSuchEntityException|\Exception
     */
    public function calculateShippingFee(AhamoveAddressInterface $ahamoveAddress, $service = null): float
    {
        try {
            $params = $this->buildParams($ahamoveAddress);

            if ($this->isDebug()) {
                $this->loggerShipping->debug(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            $content = $this->loadFromCache($params);

            // If the cache is empty or holds a previous failed result, call the API to get the shipping fee
            if (!is_array($content) || count($content) === 0) {
                $response = $this->api->name('Get shipping fee')
                    ->withContentType('application/json')
                    ->withHeader('Authorization: Bearer ' . $this->ahamoveHelper->getToken())
                    ->to(Config::SHIPPING_FEE_WITH_MANY_SERVICES)
                    ->withData($params)
                    ->asJsonResponse(true)
                    ->post();

                $content = $this->api->processResponse($response);
                // Only cache successful results. A failed result (e.g. an auth-fail status code such as 401)
                // must not be cached, otherwise the fee stays stuck at 0 until the cache TTL expires.
                if (is_array($content) && count($content) > 0) {
                    $this->cache->save($this->serializer->serialize($content), $this->ahamoveHelper->generateCacheKey($this->serializer->serialize($params)), [], 300);
                }
            }

            $shippingFee = 0;
            $servicePrice = [];
            if (is_array($content) && count($content) > 0) {
                foreach ($content as $item) {
                    if (!isset($item['data']['total_price'])) {
                        $this->loggerShipping->error(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                        continue;
                    }
                    if ($this->isStandard()) {
                        foreach (self::GROUP_STANDARD as $service) {
                            if ("$this->cityID-$service" == $item['service_id']) {
                                $shippingFee = $item['data']['total_price'];
                                $servicePrice[] = [
                                    $service => $item['data']['total_price']
                                ];
                            }
                        }
                    }
                    if ($this->isExpress()) {
                        foreach (self::GROUP_EXPRESS as $service) {
                            if ("$this->cityID-$service" == $item['service_id']) {
                                $shippingFee = $item['data']['total_price'];
                                $servicePrice[] = [
                                    $service => $item['data']['total_price']
                                ];
                            }
                        }
                    }
                }
            }
            $shippingFee = $this->calculateShippingFeeViaPackage($servicePrice);
            return $this->ahamoveHelper->convertPriceToDefaultCurrency($shippingFee);
        } catch (Exception $exception) {
            $this->_logger->error($exception->getMessage());
            return 0;
        }
    }

    /**
     * @param $request
     * @return true
     */
    protected function canDisplay($request)
    {
        if (!$this->isOnlineType()) {
            return true;
        }

        $this->package = $this->packageFactory->create();
        if ($this->isStandard()) {
            foreach (array_keys(self::GROUP_STANDARD) as $service) {
                $package = $this->getPackage($service, $request);
                if ($package && $package->count()) {
                    $this->arrayStandardServiceValid[] = $package;
                }
            }
            if (empty($this->arrayStandardServiceValid)) {
                return false;
            }
        }
        if ($this->isExpress()) {
            foreach (array_keys(self::GROUP_EXPRESS) as $service) {
                $package = $this->getPackage($service, $request);
                if ($package && $package->count()) {
                    $this->arrayExpressServiceValid[] = $package;
                }
            }
            if (empty($this->arrayExpressServiceValid)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param RateRequest $request
     * @return float
     * @throws \Exception
     */
    public function getConfigFlag($field)
    {
        if (empty($this->_code)) {
            return false;
        }
        $path = 'carriers/' . $this->_code . '/' . $field;

        return $this->_scopeConfig->isSetFlag(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
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
            return false;
        }
        $path = 'carriers/' . $this->_code . '/' . $field;

        return $this->_scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );
    }

    /**
     * @param $service
     * @param $request
     * @return Package|bool
     */
    public function getPackage($service, $request): Package|bool
    {
        $allItems = $request->getAllItems();
        $this->packageItem = $this->packageItemFactory->create();

        //Remove the ConfigurableProduct item but keep only the quantity
        $productHandle = [];
        foreach ($allItems as $item) {
            if (in_array($item->getProduct()->getSku(), array_keys($productHandle))) {
                continue;
            }
            if ($item->getProductType() === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                $productHandle[$item->getProduct()->getSku()] = $item->getQty();
            } else {
                $productHandle[$item->getSku()] = $item->getQty();
            }
        }
        foreach ($allItems as $item) {
            if ($item->getProductType() === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                continue;
            }
            if (isset($productHandle[$item->getSku()]) && $productHandle[$item->getSku()] > 0) {
                $item->setQty($productHandle[$item->getSku()]);
            }
            $product = $item->getProduct();
            $productHeight = $product->getHeight();
            $productWidth = $product->getWidth();
            $productLength = $product->getLength();

            $maximumHeight = $this->getSizeByService($service, 'height');
            $maximumWidth = $this->getSizeByService($service, 'width');
            $maximumLength = $this->getSizeByService($service, 'length');
            if ($productHeight > $maximumHeight || $productWidth > $maximumWidth || $productLength > $maximumLength) {
                return false;
            } else {
                if ($this->isStandard()) {
                    $ahamoveService = self::GROUP_STANDARD[$service];
                }
                if ($this->isExpress()) {
                    $ahamoveService = self::GROUP_EXPRESS[$service];
                }
                $this->packageItem->setServiceLength($maximumHeight)
                    ->setServiceWidth($maximumWidth)
                    ->setServiceHeight($maximumLength)
                    ->setServiceId($ahamoveService)
                    ->setCityId($this->cityID)
                    ->addItem($item);
            }
        }
        $this->package->addItem($this->packageItem);
        return $this->package;
    }

    /**
     * Check config select Ahamove API Shipping
     *
     * @return bool
     */
    public function isOnlineType(): bool
    {
        $method = $this->getConfigGeneralData('method');
        if ($method == \Secomm\Ahamove\Model\Config\Source\AhamoveMethod::API_SHIPPING) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Support get config general data
     *
     * @param string $field
     * @return mixed
     */
    public function getConfigGeneralData(string $field): mixed
    {
        return $this->_scopeConfig->getValue(
            'ahamove/general/' . $field,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return (bool)$this->getConfigGeneralData('debug');
    }

    /**
     * Support get config general data
     *
     * @param string $service
     * @param string $field
     * @return float
     */
    public function getSizeByService(string $service, string $field): float
    {
        return (float)$this->_scopeConfig->getValue(
            'ahamove/service_' . $service . '/maximum_' . $field,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getStore()
        );
    }

    /**
     * Check Quantity applies rate difference
     *
     * @param $quantity
     * @return bool
     */
    public function canDifferenceCalculated($quantity): bool
    {
        $rateDifference = (int)$this->getConfigGeneralData('quantity_applies_rate_difference');
        if ($rateDifference == 0) {
            return false;
        }
        if ($quantity > $rateDifference) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isStandard(): bool
    {
        return $this->_code == Standard::AHAMOVE_STANDARD_CARRIER_CODE;
    }

    /**
     * @return bool
     */

    public function isExpress(): bool
    {
        return $this->_code == Express::AHAMOVE_EXPRESS_CARRIER_CODE;
    }

    /**
     * @return string
     */
    protected function getCityId(): string
    {
        return (string)$this->getConfigGeneralData('city_id_service');
    }

    /**
     * Calculate package total via service price
     * Return shipping fee * package total
     *
     * @param array $servicePrice
     * @return float
     */
    public function calculateShippingFeeViaPackage(array $servicePrice): float
    {
        $min = 0;
        if (!count($servicePrice)) {
            return 0;
        }
        $services = $this->package->getItems();
        if ($this->package->count() === 0) {
            return 0;
        } else {
            $totalShippingFee = 0;
            foreach ($services as $service) {
                $numberPackage = $this->calculatePackages($service->getItems(), $service->getServiceSize());
                $serviceFee = 0;
                //Return min price
                foreach ($servicePrice as $price) {
                    if (key($price) == $service->getServiceId()) {
                        $serviceFee = $price[$service->getServiceId()];
                        break;
                    }
                }
                $totalShippingFee = $serviceFee * $numberPackage;
                if ($min == 0) {
                    $min = $totalShippingFee;
                } else {
                    if ($totalShippingFee != 0 && $totalShippingFee < $min) {
                        $min = $totalShippingFee;
                    }
                }
            }
            return $min;
        }
    }

    /**
     * Calculate packages total via quantity and rate difference
     *
     * @param mixed $quote
     * @param $serviceVolume
     * @return float|int
     */
    public function calculatePackages(mixed $quoteItems, $serviceVolume): float|int
    {
        $percentageDifference = (float)$this->getConfigGeneralData('percentage_difference') / 100;
        $productVolume = 0;
        $maximumQuantity = 0;
        foreach ($quoteItems as $quote) {
            $product = $quote->getProduct();
            $productHeight = $product->getHeight();
            $productWidth = $product->getWidth();
            $productLength = $product->getLength();
            $productVolume += ($productHeight * $productWidth * $productLength) * $quote->getQty();
            $maximumQuantity += $quote->getQty();
        }
        if (!$this->canDifferenceCalculated($maximumQuantity)) {
            $percentageDifference = 1;
        }
        $packageTotal = ceil($productVolume / $percentageDifference / $serviceVolume);
        if ($packageTotal > $maximumQuantity) {
            return $maximumQuantity;
        } else {
            return $packageTotal;
        }
    }

    /**
     * Get code.
     *
     * @param string $type
     * @param string $code
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCode($type, $code = '')
    {
        $codes = [
            'condition_name' => [
                'package_weight' => __('Weight vs. Destination'),
            ],
            'condition_name_short' => [
                'package_weight' => __('Weight (and above)'),
            ],
        ];

        if (!isset($codes[$type])) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('The "%1" code type for Table Rate is incorrect. Verify the type and try again.', $type)
            );
        }

        if ('' === $code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('The "%1: %2" code type for Table Rate is incorrect. Verify the type and try again.', $type, $code)
            );
        }

        return $codes[$type][$code];
    }

    /**
     * Get the method object based on the shipping price and cost
     *
     * @param float $shippingPrice
     * @param float $cost
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method
     */
    private function createShippingMethod($shippingPrice, $cost)
    {
        /** @var  \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->getCarrierCode());
        $method->setCarrierTitle($this->getConfigData(Config::TITLE));
        $method->setMethod($this->getCarrierCode());
        $method->setMethodTitle($this->getConfigData(Config::NAME));
        $method->setPrice($shippingPrice);
        $method->setCost($cost);
        return $method;
    }

    /**
     * Get result shipping tablerates
     *
     * @param RateRequest $request
     * @return bool|\Magento\Shipping\Model\Rate\Result
     */
    protected function getResultShippingTablerates(RateRequest $request)
    {
        $allowShippingArray = [Standard::AHAMOVE_STANDARD_CARRIER_CODE, Express::AHAMOVE_EXPRESS_CARRIER_CODE];
        if (in_array($this->getCarrierCode(), $allowShippingArray)) {
            if (!$this->isOnlineType()) {
                return $this->collectTableRates($request);
            }

            return false;
        }

        return false;
    }

    /**
     * Collect tablerates
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result
     */
    protected function collectTableRates(RateRequest $request)
    {
        // exclude Virtual products price from Package value if pre-configured
        if (!$this->getConfigFlag('include_virtual_price') && $request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getParentItem()) {
                    continue;
                }
                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    foreach ($item->getChildren() as $child) {
                        if ($child->getProduct()->isVirtual()) {
                            $request->setPackageValue($request->getPackageValue() - $child->getBaseRowTotal());
                        }
                    }
                } elseif ($item->getProduct()->isVirtual()) {
                    $request->setPackageValue($request->getPackageValue() - $item->getBaseRowTotal());
                    $request->setPackageValueWithDiscount(
                        $request->getPackageValueWithDiscount() - $item->getBaseRowTotal()
                    );
                }
            }
        }

        // Free shipping by qty
        $freeQty = 0;
        $freePackageValue = 0;
        $freeWeight = 0;
        $conventionalNumber = $this->ahamoveHelper->getConfig(self::CONFIG_CONVENTIONAL_NUMBER);
        $conventionalNumber = $conventionalNumber ?? self::DEFAULT_CONVENTIONAL_NUMBER;
        $conventionalNumber = (int)$conventionalNumber;

        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    foreach ($item->getChildren() as $child) {
                        if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                            $freeShipping = is_numeric($child->getFreeShipping()) ? $child->getFreeShipping() : 0;
                            $freeQty += $item->getQty() * ($child->getQty() - $freeShipping);
                        }
                    }
                } elseif (($item->getFreeShipping() || $item->getAddress()->getFreeShipping()) &&
                    ($item->getFreeShippingMethod() == null || $item->getFreeShippingMethod() &&
                        $item->getFreeShippingMethod() == $this->getCarrierCode())
                ) {
                    $freeShipping = $item->getFreeShipping() ?
                        $item->getFreeShipping() : $item->getAddress()->getFreeShipping();
                    $freeShipping = is_numeric($freeShipping) ? $freeShipping : 0;
                    $freeQty += $item->getQty() - $freeShipping;
                    $freePackageValue += $item->getBaseRowTotal();
                }

                if ($item->getFreeShippingMethod() && $item->getFreeShippingMethod() !== $this->getCarrierCode()) {
                    $freeWeight += (int)$this->getWeightFromDimension($item, $conventionalNumber);
                }
            }

            $request->setPackageValue($request->getPackageValue() - $freePackageValue);
            $request->setPackageValueWithDiscount($request->getPackageValueWithDiscount() - $freePackageValue);
        }

        if ($freeWeight > 0) {
            $request->setFreeMethodWeight($freeWeight);
        }

        if (!$request->getConditionName() || $request->getConditionName() != 'package_weight') {
            $conditionName = $this->getConfigData('condition_name');
            $request->setConditionName($conditionName ? $conditionName : 'package_weight');
        }

        // Package weight and qty free shipping
        $oldWeight = 0;
        foreach ($request->getAllItems() as $item) {
            $oldWeight += $this->getWeightFromDimension($item, $conventionalNumber);
        }
        $oldQty = $request->getPackageQty();
        $oldWeight = ceil($oldWeight);

        $request->setPackageWeight($oldWeight);
        $request->setPackageQty($oldQty - $freeQty);

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();
        $rate = $this->getRate($request);

        $request->setPackageWeight($oldWeight);
        $request->setPackageQty($oldQty);

        if (!empty($rate) && $rate['price'] >= 0) {
            if ($request->getPackageQty() == $freeQty) {
                $shippingPrice = 0;
            } else {
                $shippingPrice = $this->getFinalPriceWithHandlingFee($rate['price']);
            }
            $method = $this->createShippingMethod($shippingPrice, $rate['cost']);
            $result->append($method);
        } elseif ($request->getPackageQty() == $freeQty) {

            /**
             * Promotion rule was applied for the whole cart.
             *  In this case all other shipping methods could be omitted
             * Table rate shipping method with 0$ price must be shown if grand total is more than minimal value.
             * Free package weight has been already taken into account.
             */
            $request->setPackageValue($freePackageValue);
            $request->setPackageValueWithDiscount($freePackageValue);
            $request->setPackageQty($freeQty);
            $rate = $this->getRate($request);
            if (!empty($rate) && $rate['price'] >= 0) {
                $method = $this->createShippingMethod(0, 0);
                $result->append($method);
            }
        } else {
            /** @var \Magento\Quote\Model\Quote\Address\RateResult\Error $error */
            $error = $this->_rateErrorFactory->create(
                [
                    'data' => [
                        'carrier' => $this->_code,
                        'carrier_title' => $this->getDisplayName(),
                        'error_message' => $this->getConfigData('specificerrmsg'),
                    ],
                ]
            );
            $result->append($error);
        }

        return $result;
    }

    /**
     *
     * @return string
     */
    public function getDisplayName()
    {
       return $this->getConfigData(Config::TITLE) . " " . $this->getConfigData(Config::NAME);
    }

    /**
     * Get rate.
     * @param RateRequest $request
     * @return array|bool
     */
    public function getRate(RateRequest $request)
    {
        $result = [];
        try {
            $result = $this->shippingTablerates[$this->_code]->create()->getRate($request);
        } catch (\Exception $e) {
            // TODO: nothing
        }

        return $result;
    }

    /**
     * Get a weight from the dimension of item the product
     *
     * @param \Magento\Quote\Model\Quote\Item $item
     * @param int $conventionalNumber
     * @return float|int
     */
    protected function getWeightFromDimension($item, $conventionalNumber = 3000)
    {
        if ($conventionalNumber <= 0) {
            $conventionalNumber = self::DEFAULT_CONVENTIONAL_NUMBER;
        }

        $product = $item->getProduct();
        $length = $product->getLength() ?? 0;
        $width = $product->getWidth() ?? 0;
        $height = $product->getHeight() ?? 0;
        $weightFromDimension = ($length * $width * $height) / $conventionalNumber;
        $qty = (int)$item->getQty();

        $weight = $weightFromDimension * $qty;

        return $weight;
    }

    /**
     * @param AhamoveAddressInterface $ahamoveAddress
     * @return array[]
     */
    protected function buildParams(AhamoveAddressInterface $ahamoveAddress)
    {
        $services = [];
        $dataAddress = [
            [
                'address' => $ahamoveAddress->buildAddress(),
                'short_address' => '',
                'name' => '',
                'mobile' => '',
                'remarks' => ''
            ],
            [
                'address' => $ahamoveAddress->buildAddress('to'),
                'short_address' => '',
                'name' => '',
                'mobile' => ''
            ]
        ];
        $this->cityID = $this->getCityId();
        foreach (self::GROUP_STANDARD as $item) {
            $services[] = [
                "_id" => "$item"
            ];
        }
        foreach (self::GROUP_EXPRESS as $item) {
            $services[] = [
                "_id" => "$item"
            ];
        }

        return [
            'order_time' => 0,
            'path' => $dataAddress,
            'group_services' => $services,
            'payment_method' => $this->ahamoveHelper->getPaymentMethod(),
        ];
    }

    /**
     * @param $service
     * @param $package
     * @return float|int
     */
    public function estimateShippingFeeByService($service, $package)
    {
        try {
            $order = $this->orderRepository->get($package['order_id']);
            $shippingAddress = $order->getShippingAddress();
            $shippingFee = 0;
            $ahamoveAddressFactory = $this->ahamoveAddressFactory->create();
            $street = $shippingAddress->getStreet();
            $ahamoveAddressFactory->setCityTo((string)$shippingAddress->getCity())
                ->setRegionCodeTo((string)$shippingAddress->getRegion())
                ->setStreetTo((string)is_array($street) ? implode("\n", $street) : ($street ?? ''))
                ->setPostCodeTo((string)$shippingAddress->getPostcode())
                ->setNameTo('')
                ->setRemark('')
                ->setPhoneTo('');
            $ahamoveAddressFactory->setCityFrom($this->ahamoveHelper->getCity())
                ->setRegionCodeFrom((string)$this->ahamoveHelper->getShippingRegion())
                ->setStreetFrom((string)$this->ahamoveHelper->getShippingStreet())
                ->setPostCodeFrom((string)$this->ahamoveHelper->getShippingPostcode())
                ->setNameFrom('')
                ->setPhoneFrom('');
            $params = $this->buildParams($ahamoveAddressFactory);
            if ($this->isDebug()) {
                $this->loggerShipping->debug(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            $content = $this->loadFromCache($params);

            // If the cache is empty or holds a previous failed result, call the API to get the shipping fee
            if (!is_array($content) || count($content) === 0) {
                $response = $this->api->name('Get shipping fee')
                    ->withContentType('application/json')
                    ->withHeader('Authorization: Bearer ' . $this->ahamoveHelper->getToken())
                    ->to(Config::SHIPPING_FEE_WITH_MANY_SERVICES)
                    ->withData($params)
                    ->asJsonResponse(true)
                    ->post();

                $content = $this->api->processResponse($response);
                // Only cache successful results. A failed result (e.g. an auth-fail status code such as 401)
                // must not be cached, otherwise the fee stays stuck at 0 until the cache TTL expires.
                if (is_array($content) && count($content) > 0) {
                    $this->cache->save($this->serializer->serialize($content), $this->ahamoveHelper->generateCacheKey($this->serializer->serialize($params)), [], 300);
                }
            }
            $maximumHeight = $this->getSizeByService($service, 'height');
            $maximumWidth = $this->getSizeByService($service, 'width');
            $maximumLength = $this->getSizeByService($service, 'length');
            if ($this->isStandard()) {
                $service = self::GROUP_STANDARD[$service];
            } else {
                $service = self::GROUP_EXPRESS[$service];
            }
            if (is_array($content) && count($content) > 0) {
                foreach ($content as $item) {
                    if (!isset($item['data']['total_price'])) {
                        $this->loggerShipping->error(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    } else {
                        if ("$this->cityID-$service" == $item['service_id']) {
                            $shippingFee = $item['data']['total_price'];
                        }
                    }
                }
            }

            if ($shippingFee == 0) {
                return 0;
            } else {
                $serviceSize = $maximumHeight * $maximumWidth * $maximumLength;
                $items = $package['items'];
                $quoteVirtual = $this->quote->create();

                foreach ($items as $orderItemId => $qty) {
                    $product = $order->getItemById($orderItemId)->getProduct();
                    $quoteVirtual->addProduct($product, $qty);
                    $productHeight = $product->getHeight();
                    $productWidth = $product->getWidth();
                    $productLength = $product->getLength();
                    if ($productHeight > $maximumHeight || $productWidth > $maximumWidth || $productLength > $maximumLength) {
                        return 0;
                    }
                }
                $numberPackages = $this->calculatePackages($quoteVirtual->getAllItems(), $serviceSize);
            }
            return $shippingFee * $numberPackages;
        } catch (\Exception $exception) {
            $this->_logger->error($exception->getMessage());
            return 0;
        }
    }

    public function createShipment($package)
    {
        $service = $package->getService();
        if ($this->isStandard()) {
            $service = self::GROUP_STANDARD[$service];
        } else {
            $service = self::GROUP_EXPRESS[$service];
        }
        $cityServiceId = $this->getCityId() . '-' . strtoupper($service);
        $package->setService($cityServiceId);
        $result = $this->createShipment->execute($package);
        if ($result) {
            $package->setShippingAmount($result['order']['total_price']);
            $package->setTrackNumber($result['order']['tracking_code']);
            $package->setServiceOrderId($result['order']['_id']);
            $package->setStatus($result['order']['status']);
            $package->setStatusLabel($result['order']['status_label']);
        } else {
            return $package;
        }
        return $package;
    }
}
