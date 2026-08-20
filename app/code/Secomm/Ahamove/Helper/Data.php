<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Helper;

use Magento\Checkout\Model\Session;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\PriceCurrency;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\ResourceModel\Currency;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\Ahamove\Model\Config;
use Secomm\Ahamove\Model\Config\Source\Mode;

class Data extends AbstractHelper
{
    const CONVERSION_RATES = [
        'kgs' => 1,
        'lbs' => 0.453592,
    ];

    protected string $scope = ScopeInterface::SCOPE_STORE;

    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var \Magento\Framework\App\Cache\Frontend\Pool
     */
    protected $cacheFrontendPool;
    protected $inlineTranslation;
    protected $transportBuilder;
    protected $senderResolver;

    public function __construct(
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Cache\Frontend\Pool     $cacheFrontendPool,
        protected PriceCurrency                        $priceCurrency,
        protected CurrencyFactory                      $currencyFactory,
        protected StoreManagerInterface                $storeManager,
        protected Currency                             $currency,
        protected CountryFactory                       $countryFactory,
        protected RegionFactory                        $regionFactory,
        protected Session                              $checkoutSession,
        protected \Magento\Framework\Module\Dir\Reader $moduleDir,
        Context                                        $context,
        StateInterface                  $inlineTranslation,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\Mail\Template\SenderResolverInterface $senderResolver
    ) {
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->inlineTranslation = $inlineTranslation;
        $this->transportBuilder = $transportBuilder;
        $this->senderResolver = $senderResolver;
        parent::__construct($context);
    }

    /**
     * Get URL connect Ahamove server.
     *
     * @return mixed
     */
    public function getUrlAhamove($storeId = null)
    {
        if ($this->getMode($storeId) == Mode::SANDBOX_MODE) {
            return Config::URL_SANDBOX;
        }
        return Config::URL_PRODUCTION;
    }

    /**
     * Get Sandbox Mode.
     *
     * @param mixed $storeId
     * @return mixed
     */
    public function getMode($storeId = null)
    {
        return $this->getConfig(Config::MODE_PATH, $storeId);
    }

    /**
     * Get API_key for connect refresh.
     *
     * @param mixed $storeId
     * @return mixed
     */
    public function getAPIKey($storeId = null)
    {
        if ($this->isStagingMode($storeId)) {
            return $this->getConfig(Config::STAGING_API_KEY, $storeId);
        } else {
            return $this->getConfig(Config::PRODUCTION_API_KEY, $storeId);
        }
    }

    /**
     * @param mixed $storeId
     * @return bool
     */
    public function isStagingMode($storeId = null): bool
    {
        return $this->getMode($storeId) == Mode::SANDBOX_MODE;
    }

    /**
     * Get Token for connect API.
     *
     * @param mixed $storeId
     * @return mixed
     */
    public function getToken($storeId = null)
    {
        if ($this->isStagingMode($storeId)) {
            return $this->getConfig(Config::STAGING_TOKEN, $storeId);
        } else {
            return $this->getConfig(Config::PRODUCTION_TOKEN, $storeId);
        }
    }

    /**
     * Get Phone number of account ahamove.
     *
     * @param mixed $storeId
     * @return mixed
     */
    public function getMobilePhoneValue($storeId = null)
    {
        if ($this->isStagingMode($storeId)) {
            return $this->getConfig(Config::STAGING_PHONE_NUMBER, $storeId);
        } else {
            return $this->getConfig(Config::PRODUCTION_PHONE_NUMBER, $storeId);
        }
    }

    /**
     * Get website identifier
     *
     * @return string|int|null
     * @throws NoSuchEntityException
     */
    public function getWebsiteId()
    {
        return $this->storeManager->getStore()->getWebsiteId();
    }

    /**
     * Get website identifier
     *
     * @return string|int|null
     */
    public function getStoreName()
    {
        return $this->storeManager->getStore()->getName();
    }

    /**
     * Get Payment type of account ahamove.
     *
     * @return mixed
     */
    public function getAhamoveCityIdOfService()
    {
        return $this->getConfig(Config::AHAMOVE_GENERAL_CITY_ID_SERVICE);
    }

    /**
     * Get setting for notify for shipment email.
     *
     * @return mixed
     */
    public function getAhamoveNotifyShipment()
    {
        return $this->getConfig(Config::AHAMOVE_GENERAL_NOTIFY_SHIPMENT);
    }

    /**
     * @return mixed
     */
    public function getPaymentMethod()
    {
        return $this->getConfig(Config::AHAMOVE_GENERAL_PAYMENT_TYPE);
    }

    /**
     * Get Config with path
     *
     * @param string $path
     * @param mixed $storeId
     * @return mixed
     */
    public function getConfig(string $path, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * flushCache after change config
     *
     * @return void
     */
    public function flushCache(): void
    {
        $this->cacheTypeList->cleanType('config');
    }

    /**
     * @param $shippingFee
     * @return float|int
     */
    public function convertPriceToDefaultCurrency($shippingFee)
    {
        try {
            $defaultCurrencyCode = $this->getDefaultCurrencyCode();
            if ($defaultCurrencyCode != 'VND') {
                $rate = $this->currency->getRate($defaultCurrencyCode, 'VND');
                return $this->priceCurrency->roundPrice($shippingFee / $rate);
            } else {
                $baseCurrencyCode = $this->getBaseCurrencyCode();
                $rate = $this->currency->getRate($baseCurrencyCode, 'VND');
                return $this->priceCurrency->roundPrice($shippingFee / $rate);
            }
        } catch (\Exception $exception) {
            $this->_logger->error($exception->getMessage());
            return 0;
        }
    }

    /**
     * @param $productPrice
     * @return float|int
     */
    public function convertPriceProductToDefaultCurrency($productPrice)
    {
        try {
            $baseCurrencyCode = $this->getDefaultCurrencyCode();
            if ($baseCurrencyCode != 'VND') {
                $rate = $this->currency->getRate($baseCurrencyCode, 'VND');
                return $this->priceCurrency->roundPrice($productPrice * $rate);
            } else {
                return $this->priceCurrency->roundPrice($productPrice);
            }
        } catch (\Exception $exception) {
            $this->_logger->error($exception->getMessage());
            return 0;
        }
    }

    /**
     * @return string|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getDefaultCurrencyCode(): ?string
    {
        return $this->storeManager->getStore()->getDefaultCurrencyCode();
    }

    /**
     * @return string|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getBaseCurrencyCode(): ?string
    {
        return $this->storeManager->getStore()->getBaseCurrencyCode();
    }

    /**
     * Get shipping country name
     * @return string
     */
    public function getShippingCountry()
    {
        $countryCode = $this->getConfig('shipping/origin/country_id');
        $country = $this->countryFactory->create()->loadByCode($countryCode);
        return $country->getName();
    }

    /**
     * Get shipping region name
     * @return string
     */
    public function getShippingRegion(): string
    {
        try {
            $regionId = $this->getConfig('shipping/origin/region_id');
            $region = $this->regionFactory->create()->load($regionId);
            return (string)($region->getName() ?: $regionId);
        } catch (\Exception $exception) {
            return '';
        }
    }

    /**
     * Get shipping region name
     * @return string
     */
    public function getCity(): string
    {
        return (string)$this->getConfig('shipping/origin/city');
    }

    /**
     * Get shipping postcode
     * @return string
     */
    public function getShippingPostcode(): string
    {
        return $this->getConfig('shipping/origin/postcode');
    }

    /**
     * Get shipping country name
     * @return string
     */
    public function getShippingCountryName(): string
    {
        return $this->getShippingCountryNameByCode($this->getConfig('shipping/origin/country_id'));
    }

    public function getShippingCountryNameByCode(string $countryCode): string
    {
        try {
            $country = $this->countryFactory->create()->loadByCode($countryCode);
            return (string)$country->getName();
        } catch (\Exception $exception) {
            return $countryCode;
        }
    }

    /**
     * Get shipping postcode
     * @return string
     */
    public function getShippingStreet(): string
    {
        return (string)$this->getConfig('shipping/origin/street_line1');
    }

    /**
     * Generate a unique cache key based on the serialized request parameters.
     *
     * @param string $serialize
     * @return string
     */
    public function generateCacheKey(string $serialize): string
    {
        return md5($serialize);
    }

    /**
     * Get the current weight unit from Magento configuration
     *
     * @return string
     */
    public function getCurrentWeightUnit(): string
    {
        return $this->scopeConfig->getValue(
            \Magento\Directory\Helper\Data::XML_PATH_WEIGHT_UNIT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get the current weight unit from Magento configuration and convert the value to kilograms
     * @docs Unit measurement https://developers.ahamove.com/docs-onwheel/operator-web-application/pricing-profile
     *
     * @param float $value The weight value to convert
     * @return mixed The converted weight value in kilograms
     */
    public function convertToKilograms($value): float
    {
        $value = (float)$value;
        // Get the weight unit from the configuration
        $weightUnit = $this->getCurrentWeightUnit();

        // Convert the value to kilograms based on the weight unit
        if (isset(self::CONVERSION_RATES[$weightUnit])) {
            return $value * self::CONVERSION_RATES[$weightUnit];
        }

        return $value;
    }

    /**
     * Get status label of ahamove service
     * @param string $statusCode
     * @return string
     * @throws \Exception
     */
    public function getStatusLabel($statusCode = null)
    {
        $statusLabel = '';
        try {
            $filePath = $this->moduleDir->getModuleDir('', 'Secomm_Ahamove') . '/File/shipping_status.json';
            $jsonData = file_get_contents($filePath);
            $dataShippingStatus = json_decode($jsonData, true);

            if (count($dataShippingStatus) == 0) {
                return $statusLabel;
            }

            foreach ($dataShippingStatus as $data) {
                if (strtoupper($data['status_code']) == strtoupper($statusCode)) {
                    $statusLabel = $data['title'];
                    break;
                }
            }

            return $statusLabel;
        } catch (\Exception $e) {
        }

        return $statusLabel;
    }

    /**
     * Get Value By Path
     * @param $path
     * @param null $store
     * @return mixed
     */
    public function getValueByPath($path, $scopeId = null)
    {
        $sender = $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE
        );
        $result = [];

        if (!is_array($sender)) {
            if (!isset($result['name']) || !isset($result['email'])) {
                $result = $this->senderResolver->resolve($sender);
            }
        } else {
            $result = $sender;
        }

        if (!isset($result['name']) || !isset($result['email'])) {
            throw new \Magento\Framework\Exception\MailException(__('Invalid sender data'));
        }

        return $result;
    }

    /**
    * Send a notify webhook ahamove
    *
    * @param string $content
    * @return void
    */
    public function sendNotifyWebhookAhamove($content)
    {
        $this->inlineTranslation->suspend();
        $storeId = $this->storeManager->getStore(true)->getId();
        try {
            $emailFrom = $this->getValueByPath(Config::AHAMOVE_GENERAL_EMAIL_FROM);
            $transportSeller = $this->transportBuilder->setTemplateIdentifier(
                'carriers_ahamove_general_email_notify_webhook'
            )->setTemplateOptions(
                ['area' => Area::AREA_FRONTEND,'store' => $storeId]
            )->setTemplateVars(
                [
                    'content'=> $content
                ]
            )->setFrom(
                $this->getValueByPath(Config::AHAMOVE_GENERAL_EMAIL_FROM)
            )->addTo(
                $emailFrom['email']
            )->getTransport();
            $transportSeller->sendMessage();
        } catch (\Magento\Framework\Exception\MailException $ex) {
        }
        $this->inlineTranslation->resume();
    }

    /**
     * Resolves Ahamove Service ID using constants from AhamoveShippingMethod
     * (e.g. SGN-BIKE, SGN-VAN-500, SGN-2H-PUBLIC)
     *
     * @param string $shippingMethod
     * @param mixed $storeId
     * @return string
     */
    public function resolveServiceId(string $shippingMethod, $storeId = null): string
    {
        $cityId = (string)$this->getConfig(Config::AHAMOVE_GENERAL_CITY_ID_SERVICE, $storeId);
        if (empty($cityId)) {
            $cityId = 'SGN';
        }

        $serviceCode = '';
        $shippingMethodLower = strtolower($shippingMethod);

        if (str_contains($shippingMethodLower, 'standard')) {
            foreach (\Secomm\Ahamove\Model\Carrier\ShippingMethod\AhamoveShippingMethod::GROUP_STANDARD as $subMethod => $ahaCode) {
                if (str_contains($shippingMethodLower, $subMethod)) {
                    $serviceCode = $ahaCode;
                    break;
                }
            }
            if (empty($serviceCode)) {
                $serviceCode = \Secomm\Ahamove\Model\Carrier\ShippingMethod\AhamoveShippingMethod::GROUP_STANDARD['bike'];
            }
        } elseif (str_contains($shippingMethodLower, 'express')) {
            foreach (\Secomm\Ahamove\Model\Carrier\ShippingMethod\AhamoveShippingMethod::GROUP_EXPRESS as $subMethod => $ahaCode) {
                if (str_contains($shippingMethodLower, $subMethod)) {
                    $serviceCode = $ahaCode;
                    break;
                }
            }
            if (empty($serviceCode)) {
                $serviceCode = \Secomm\Ahamove\Model\Carrier\ShippingMethod\AhamoveShippingMethod::GROUP_EXPRESS['two_hours'];
            }
        } else {
            $serviceCode = 'BIKE';
        }

        return strtoupper($cityId . '-' . $serviceCode);
    }
}
