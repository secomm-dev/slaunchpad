<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Request;

use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Store\Model\Information;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\GhnAddressMapper\Api\LocationResolverInterface;
use Secomm\GiaoHangNhanh\Helper\Rate;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\ConfigInterface;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Request\BuilderInterface;
use Secomm\GiaoHangNhanh\Model\Config;

/**
 * Class AbstractDataBuilder
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Request
 */
abstract class AbstractDataBuilder implements BuilderInterface
{
    const DEFAULT_WEIGHT_UNIT = 'kgs';
    const TOKEN = 'token';
    const ORDER_CODE = 'order_code';
    const WEIGHT = 'weight';
    const FROM_DISTRICT_ID = 'from_district_id';
    const TO_DISTRICT_ID = 'to_district_id';
    const PAYMENT_TYPE_ID = 'payment_type_id';
    const SERVICE_ID = 'service_id';
    const LENGTH = 'length';
    const WIDTH = 'width';
    const HEIGHT = 'height';
    const CO_D_AMOUNT = 'cod_amount';
    const RETURN_PHONE = 'return_phone';
    const RETURN_ADDRESS = 'return_address';
    const RETURN_DISTRICT_ID = 'return_district_id';
    const RETURN_WARD_CODE = 'return_ward_code';
    const CLIENT_ORDER_CODE = 'client_order_code';
    const FROM_DISTRICT = 'from_district';
    const TO_DISTRICT = 'to_district';
    const SHOP_ID = 'shop_id';
    const INSURANCE_VALUE = 'insurance_value';
    const COD_FAILED_AMOUNT = 'cod_failed_amount';
    const COUPON = 'coupon';
    const TO_NAME = 'to_name';
    const TO_PHONE = 'to_phone';
    const TO_ADDRESS = 'to_address';
    const TO_WARD_CODE = 'to_ward_code';
    const FROM_WARD_CODE = 'from_ward_code';
    const SERVICE_TYPE_ID = 'service_type_id';
    const REQUIRED_NOTE = 'required_note';
    const NOTE = 'note';
    const FROM_NAME = 'from_name';
    const FROM_PHONE = 'from_phone';
    const FROM_ADDRESS = 'from_address';
    const FROM_WARD_NAME = 'from_ward_name';
    const FROM_DISTRICT_NAME = 'from_district_name';
    const FROM_PROVINCE_NAME = 'from_province_name';
    const CONTENT = 'content';
    const PICK_SHIFT = 'pick_shift';
    const DELIVER_STATE_ID = 'deliver_station_id';
    const ITEMS = 'items';
    const ORDER_CODES = 'order_codes';
    const DISTRICT_ID = 'district_id';
    const COD_VALUE = 'cod_value';

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Information
     */
    protected $storeInformation;

    /**
     * @var AddressFactory
     */
    protected $addressFactory;

    /**
     * @var Config
     */
    protected $baseConfig;

    /**
     * @var Rate
     */
    protected $helperRate;

    /**
     * @var LocationResolverInterface
     */
    protected LocationResolverInterface $locationResolver;

    /**
     * AbstractDataBuilder constructor.
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param Information $storeInformation
     * @param AddressFactory $addressFactory
     * @param Config $baseConfig
     * @param Rate $helperRate
     * @param LocationResolverInterface $locationResolver
     */
    public function __construct(
        ConfigInterface $config,
        StoreManagerInterface $storeManager,
        Information $storeInformation,
        AddressFactory $addressFactory,
        Config $baseConfig,
        Rate $helperRate,
        LocationResolverInterface $locationResolver
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->storeInformation = $storeInformation;
        $this->addressFactory = $addressFactory;
        $this->baseConfig = $baseConfig;
        $this->helperRate = $helperRate;
        $this->locationResolver = $locationResolver;
    }

    protected function getIsDevelopMode()
    {
        return $this->config->getValue('is_develop_mode');
    }

    /**
     * Resolve GHN location (District ID & Ward Code) from Region ID, City ID, or City Name
     *
     * @param int $regionId
     * @param string $city
     * @param int $cityId
     * @return array{toDistrictId: ?int, toWardCode: string}
     */
    protected function resolveGhnLocation(int $regionId, string $city = '', int $cityId = 0): array
    {
        $toDistrictId = null;
        $toWardCode = '';

        if ($regionId && ($cityId || $city !== '')) {
            try {
                if ($cityId) {
                    $result = $this->locationResolver->resolve($regionId, $cityId);
                } else {
                    $result = $this->locationResolver->resolveByName($regionId, $city);
                }
                $toDistrictId = (int)$result->getDistrictId();
                $toWardCode = (string)$result->getWardCode();
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                if ($this->getIsDevelopMode()) {
                    $toDistrictId = 1456;
                    $toWardCode = '21511';
                }
            }
        } elseif ($this->getIsDevelopMode()) {
            $toDistrictId = 1456;
            $toWardCode = '21511';
        }

        return [
            'toDistrictId' => $toDistrictId,
            'toWardCode' => $toWardCode
        ];
    }
}
