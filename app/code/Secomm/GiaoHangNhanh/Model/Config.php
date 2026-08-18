<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model;

use Secomm\GiaoHangNhanh\Model\ResourceModel\District as DistrictResource;
use Magento\Directory\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Config
 *
 * @package Secomm\GiaoHangNhanh\Model
 */
class Config
{
    const DEFAULT_PATH_PATTERN = 'giaohangnhanh_setting/%s/%s';
    const INTEGRATION_TYPE = 'general';
    const GHN_CODE = 'giaohangnhanh';
    const LBS_G = 453.59237;
    const KGS_G = 1000;
    const IS_ACTIVE = 'active';
    const TITLE = 'title';
    const NAME = 'name';
    const CALCULATING_FEE_URL = 'calculate_fee_url';
    const SYNCHRONIZING_ORDER_URL = 'sync_order_url';
    const GETTING_DISTRICTS_URL = 'get_districts_url';
    const GETTING_SERVICES_URL = 'get_services_url';
    const GETTING_ORDER_INFOR = 'get_order_infor_url';
    const CANCELING_ORDER_URL = 'cancel_order_url';
    const GETTING_PROVINCES_URL = 'get_provinces_url';
    const GETTING_WARDS_URL = 'get_wards_url';
    const XML_PATH_AUTO_SYNC_ON_PLACE_ORDER = 'giaohangnhanh_setting/general/auto_sync_on_place_order';
    const XML_PATH_ENABLE_ADMIN_MANUAL_SYNC_BUTTON = 'giaohangnhanh_setting/general/enable_admin_manual_sync_button';
    const XML_PATH_AUTO_SYNC_ON_SHIPMENT_CREATE = 'giaohangnhanh_setting/general/auto_sync_on_shipment_create';
    const XML_PATH_SYNC_MODE = 'giaohangnhanh_setting/general/sync_mode';

    /**
     * @var int
     */
    protected $storeId;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var DistrictResource
     */
    private $districtResource;

    /**
     * Config constructor.
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param DistrictResource $districtResource
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        DistrictResource $districtResource
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->districtResource = $districtResource;
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isAutoSyncOnPlaceOrder(): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(
            self::XML_PATH_AUTO_SYNC_ON_PLACE_ORDER,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isEnableAdminManualSyncButton(): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_ADMIN_MANUAL_SYNC_BUTTON,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isAutoSyncOnShipmentCreate(): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(
            self::XML_PATH_AUTO_SYNC_ON_SHIPMENT_CREATE,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    /**
     * @return string
     */
    public function getSyncMode(): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::XML_PATH_SYNC_MODE,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        ) ?: \Secomm\GiaoHangNhanh\Model\Config\Source\SyncMode::SYNC_MODE_ASYNC);
    }

    /**
     * @return bool
     */
    public function isDirectSyncMode(): bool
    {
        return $this->getSyncMode() === \Secomm\GiaoHangNhanh\Model\Config\Source\SyncMode::SYNC_MODE_DIRECT;
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getWeightUnit()
    {
        return $this->getConfig(Data::XML_PATH_WEIGHT_UNIT);
    }

    /**
     * @return array
     */
    public function getDistricts()
    {
        return $this->districtResource->getDistrictsWithRegion();
    }

    /**
     * @return array
     */
    public function getDistrictOptions()
    {
        $districts = $this->getDistricts();
        $data = [];
        foreach ($districts as $district) {
            $districtName = $district['district_name'];
            $data[] = [
                'title' => $districtName,
                'value' => $district['district_id'],
                'region_id' => $district['region_id'],
                'label' => $districtName
            ];
        }
        return $data;
    }

    /**
     * @param $path
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getConfig($path)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    private function getStoreId()
    {
        if (!$this->storeId) {
            $this->storeId = $this->storeManager->getStore()->getStoreId();
        }
        return $this->storeId;
    }
}
