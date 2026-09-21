<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Store\Model\Information;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Secomm\GhnAddressMapper\Api\LocationResolverInterface;
use Secomm\GiaoHangNhanh\Helper\Rate;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\ConfigInterface;
use Secomm\GiaoHangNhanh\Model\Config;
use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Model\ShippingContextFactory;

/**
 * Class ShippingDetailsDataBuilder
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Request
 */
class ShippingDetailsDataBuilder extends AbstractDataBuilder
{
    public function __construct(
        ConfigInterface       $config,
        StoreManagerInterface $storeManager,
        Information           $storeInformation,
        AddressFactory        $addressFactory,
        Config                $baseConfig,
        Rate                  $helperRate,
        LocationResolverInterface $locationResolver,
        LoggerInterface $logger,
        private readonly ShippingContextFactory $shippingContextFactory,
        private readonly OriginProviderInterface $originProvider
    ) {
        parent::__construct(
            $config,
            $storeManager,
            $storeInformation,
            $addressFactory,
            $baseConfig,
            $helperRate,
            $locationResolver,
            $logger
        );
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        $rateRequest = SubjectReader::readRateRequest($buildSubject);
        $rate = $this->baseConfig->getWeightUnit() == self::DEFAULT_WEIGHT_UNIT ? Config::KGS_G : Config::LBS_G;

        $shippingAddress = $rateRequest->getShippingAddress();
        $regionId = $shippingAddress ? (int)$shippingAddress->getRegionId() : (int)$rateRequest->getDestRegionId();
        $city = $shippingAddress ? (string)$shippingAddress->getCity() : (string)$rateRequest->getDestCity();
        $cityId = $shippingAddress ? (int)$shippingAddress->getData('city_id') : (int)$rateRequest->getData('city_id');
        $ward = $shippingAddress ? (string)$shippingAddress->getData('sub_city') : '';

        // Resolve origin via Secomm_ShippingCore
        $context = $this->shippingContextFactory->fromRateRequest($rateRequest, Config::GHN_CODE);
        $origin = $this->originProvider->resolve($context);
        $locationFrom = $this->resolveGhnLocation((int)$origin->getRegionId(), (string) $origin->getWard());
        $fromDistrictId = $locationFrom['toDistrictId'];
        $fromWardCode = $locationFrom['toWardCode'];

        if ($this->getIsDevelopMode()) {
            $fromDistrictId = 1457;
            $fromWardCode = '21715';
        }

        $locationTo = $this->resolveGhnLocation((int)$regionId, $city, $cityId);
        $toDistrictId = $locationTo['toDistrictId'];
        $toWardCode = $locationTo['toWardCode'];

        // (float) cast: getPackage*() may be null (e.g. cart-add collects rates
        // before package dims are set) — PHP 8 ceil(null) throws TypeError.
        $length = ceil((float)$rateRequest->getPackageLength());
        $width = ceil((float)$rateRequest->getPackageWidth());
        $height = ceil((float)$rateRequest->getPackageHeight());

        $data = [
            self::TOKEN => $this->config->getValue('api_token'),
            self::SERVICE_TYPE_ID => null,
            self::TO_DISTRICT_ID => $toDistrictId,
            self::TO_WARD_CODE => $toWardCode,
            self::WEIGHT => (int)($rateRequest->getPackageWeight() * $rate),
            self::LENGTH => $length,
            self::WIDTH => $width,
            self::HEIGHT => $height,
            self::COUPON => null,
            self::SHOP_ID => (int)$this->config->getValue('shop_id')
        ];
        if ($fromDistrictId) {
            $data[self::FROM_DISTRICT_ID] = $fromDistrictId;
        }
        if ($fromWardCode) {
            $data[self::FROM_WARD_CODE] = $fromWardCode;
        }

        if ($serviceId = SubjectReader::readServiceId($buildSubject)) {
            $data[self::SERVICE_ID] = $serviceId;
        }

        $data['items'] = $this->getAllItems($rateRequest->getAllItems());

        // payment COD
        if (isset($buildSubject['total_order'])) {
            $data[self::COD_VALUE] = $buildSubject['total_order'];
        }

        return $data;
    }

    /**
     * Get all items of order
     * @param \Magento\Quote\Model\Quote\Item[] $items
     * @return array
     */
    public function getAllItems($items)
    {
        $result = [];
        if (count($items) > 0) {
            $weightRate = $this->baseConfig->getWeightUnit() == self::DEFAULT_WEIGHT_UNIT ? Config::KGS_G : Config::LBS_G;
            /**
             * Required input Item when choosing a traditional delivery service
             * 5: Traditional Delivery
             * 2: E-commerce Delivery
             */
            foreach ($items as $item) {
                $tmp = [];
                $tmp['name'] = $item->getName();
                $tmp['code'] = $item->getSku();
                $tmp['quantity'] = (int)$item->getQty();
                // GHN items[] values are per-unit (GHN multiplies by quantity itself)
                $tmp['weight'] = ceil($item->getWeight() * $weightRate);
                $tmp['width'] = $item->getWidth() !== null ? (int)ceil((float)$item->getWidth()) : 0;
                $tmp['height'] = $item->getHeight() !== null ? (int)ceil((float)$item->getHeight()) : 0;
                $tmp['length'] = $item->getLength() !== null ? (int)ceil((float)$item->getLength()) : 0;

                array_push($result, $tmp);
            }
        }

        return $result;
    }
}
