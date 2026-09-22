<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */

namespace Secomm\GiaoHangNhanh\Model\Service\Request;

use Secomm\GhnAddressMapper\Api\LocationResolverInterface;
use Secomm\GiaoHangNhanh\Helper\Rate;
use Secomm\GiaoHangNhanh\Model\Config;
use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\ConfigInterface;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Model\ShippingContextFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Store\Model\Information;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class SynchronizeOrderDataBuilder
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Request
 */
class SynchronizeOrderDataBuilder extends AbstractDataBuilder
{
    /**
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param Information $storeInformation
     * @param AddressFactory $addressFactory
     * @param Config $baseConfig
     * @param Rate $helperRate
     * @param LocationResolverInterface $locationResolver
     * @param ShippingContextFactory $shippingContextFactory
     * @param OriginProviderInterface $originProvider
     */
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
    )
    {
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
     * @throws LocalizedException
     */
    public function build(array $buildSubject)
    {
        $order = SubjectReader::readOrder($buildSubject);
        $weightRate = $this->baseConfig->getWeightUnit() == self::DEFAULT_WEIGHT_UNIT ? Config::KGS_G : Config::LBS_G;
        $store = $this->storeManager->getStore();
        $storeInfo = $this->storeInformation->getStoreInformationObject($store);

        // Resolve origin via Secomm_ShippingCore
        $context = $this->shippingContextFactory->create(
            storeId: (int)$store->getId(),
            carrierCode: Config::GHN_CODE,
            quoteId: $order->getQuoteId() !== null ? (int)$order->getQuoteId() : null
        );
        $origin = $this->originProvider->resolve($context);

        $fromWardName = $origin->getWard() ?? '';
        $fromProvinceName = $origin->getProvince() ?? '';
        $fromDistrictName = $origin->getDistrict() ?? '';
        $fromAddress = $this->getFromAddress($origin); // $origin->getStreet() ??

        if ($this->getIsDevelopMode()) {
            $fromWardName = 'Phường 17';
            $fromDistrictName = 'Quận Phú Nhuận';
            $fromProvinceName = 'Hồ Chí Minh';
        }

        $shippingAddress = $order->getShippingAddress();
        $regionId = $shippingAddress ? (int)$shippingAddress->getRegionId() : 0;
        $city = $shippingAddress ? (string)$shippingAddress->getCity() : '';
        $cityId = $shippingAddress ? (int)$shippingAddress->getData('city_id') : 0;
        $ward = $shippingAddress ? (string)$shippingAddress->getData('sub_city') : '';

        $location = $this->resolveGhnLocation($regionId, $city, $cityId);
        $toDistrictId = $location['toDistrictId'] ?? 0;
        $toWardCode = $location['toWardCode'] ?? "";

        $toName = $order->getCustomerName();
        $toPhone = $shippingAddress ? $shippingAddress->getTelephone() : '';
        $toAddress = $this->getToAddress($shippingAddress);
        $serviceTypeId = (int)SubjectReader::readShippingServiceTypeId($buildSubject);
        $items = $this->getItems($order);
        $clientOrderCode = $order->getIncrementId();

        $note = '';
        // Root dims = per-axis sum of item dims × qty — same convention as
        // Secomm\Base\Plugin\Model\Shipping::getTotal() used for rate requests.
        $length = $width = $height = 0;
        foreach ($items as $item) {
            $length += $item['length'] * $item['quantity'];
            $width += $item['width'] * $item['quantity'];
            $height += $item['height'] * $item['quantity'];
        }
        $length = (int)ceil($length);
        $width = (int)ceil($width);
        $height = (int)ceil($height);

        $data = [
            self::TOKEN => $this->config->getValue('api_token'),
            self::SHOP_ID => (int)$this->config->getValue('shop_id'),
            self::PAYMENT_TYPE_ID => (int)$this->config->getValue('payment_type'),
            self::REQUIRED_NOTE => $this->config->getValue('note_code'),
            self::FROM_NAME => $storeInfo->getName(),
            self::FROM_PHONE => $storeInfo->getPhone(),
            self::FROM_ADDRESS => $fromAddress,
            self::FROM_WARD_NAME => $fromWardName,
            self::FROM_DISTRICT_NAME => $fromDistrictName,
            self::FROM_PROVINCE_NAME => $fromProvinceName,
            self::SERVICE_ID => (int)SubjectReader::readShippingServiceId($buildSubject),
            self::SERVICE_TYPE_ID => $serviceTypeId,
            self::WEIGHT => (int)($order->getWeight() * $weightRate),
            self::LENGTH => $length,
            self::WIDTH => $width,
            self::HEIGHT => $height,
            self::TO_NAME => $toName,
            self::TO_PHONE => $toPhone,
            self::TO_ADDRESS => $toAddress,
            self::TO_WARD_CODE => $toWardCode,
            self::TO_DISTRICT_ID => $toDistrictId,
            self::COUPON => '',
            self::ITEMS => $items,
            self::CLIENT_ORDER_CODE => $clientOrderCode,
            self::NOTE => $note,
        ];

        // Has the order with payment COD?
        if (isset($buildSubject['is_order_payment_cod']) && $buildSubject['is_order_payment_cod']) {
            $data[self::CO_D_AMOUNT] = (int)$this->helperRate->getVndOrderAmount($order, $order->getGrandTotal());
        }

        return $data;
    }

    /**
     * Get items of order
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getItems($order)
    {
        $items = [];
        if (count($order->getItems()) > 0) {
            $weightRate = $this->baseConfig->getWeightUnit() == self::DEFAULT_WEIGHT_UNIT ? Config::KGS_G : Config::LBS_G;
            /**
             * Required input Item when choosing a traditional delivery service
             * 5: Traditional Delivery
             * 2: E-commerce Delivery
             */
            foreach($order->getItems() as $itemOrder){
                $product = $itemOrder->getProduct();
                $price = $this->helperRate->getVndAmountByStoreCurrency($itemOrder->getPrice() * $itemOrder->getQtyOrdered());
                $item = [];
                $item['name'] = $itemOrder->getName();
                $item['code'] = $itemOrder->getSku();
                $item['quantity'] = (int)$itemOrder->getQtyOrdered();
                $item['price'] = $price;
                // GHN items[].weight is per-unit (docs example: item 300g x qty 2 = root 600g total)
                $item['weight'] = ceil($itemOrder->getWeight() * $weightRate);
                $item['width'] = $product->getWidth() !== null ? (int)ceil((float)$product->getWidth()) : 0;
                $item['height'] = $product->getHeight() !== null ? (int)ceil((float)$product->getHeight()) : 0;
                $item['length'] = $product->getLength() !== null ? (int)ceil((float)$product->getLength()) : 0;

                array_push($items, $item);
            }
        }

        return $items;
    }

    /**
     * Get address of storeInfo
     * @param $origin
     * @return string
     */
    public function getFromAddress($origin)
    {
        if (!$origin) {
            return '';
        }
        return $origin->getStreet() . ', ' . $origin->getWard() . ', ' . $origin->getProvince() . ', ' . $origin->getCountryId();
    }

    /**
     * Get address of shippingAddress
     * @param \Magento\Quote\Model\Quote\Address $shippingAddress
     * @return string
     */
    public function getToAddress($shippingAddress)
    {
        if (!$shippingAddress) {
            return '';
        }

        $streetLines = $shippingAddress->getStreet();
        $address = implode(', ', array_filter($streetLines)) . ', ' . $shippingAddress->getCity() . ', ' . $shippingAddress->getRegion() . ', ' . $shippingAddress->getCountryId();

        return $address ? $address : '';
    }
}
