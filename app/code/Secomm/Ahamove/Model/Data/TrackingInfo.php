<?php

namespace Secomm\Ahamove\Model\Data;

use Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard;
use Secomm\Ahamove\Model\Carrier\ShippingMethod\Express;

class TrackingInfo extends \Secomm\PackagingManager\Model\Data\TrackingInfo
{
    protected $api;
    protected $config;

    public function __construct(
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Shipping\Model\Config $shippingConfig,
        \Secomm\Ahamove\Model\Connect\Api $api,
        \Secomm\Ahamove\Helper\Data $config,
        array $shippingMethodList = []
    ) {
        parent::__construct($trackStatusFactory, $trackFactory, $scopeConfig, $shippingConfig, $shippingMethodList);
        $this->api = $api;
        $this->config = $config;
    }
    /**
     * {@inheritDoc}
     */
    public function getTrackNumber()
    {
        $trackingInfo = $this->getTrackingInfo();
        if (in_array($trackingInfo['shipping_method'], 
            [Standard::AHAMOVE_STANDARD_CARRIER_CODE, Express::AHAMOVE_EXPRESS_CARRIER_CODE])) {
            return $trackingInfo['service_order_id'];
        }

        return parent::getTrackNumber();
    }

    /**
     * {@inheritDoc}
     */
    public function getModuleName()
    {
        $trackingInfo = $this->getTrackingInfo();
        if (in_array($trackingInfo['shipping_method'], 
            [Standard::AHAMOVE_STANDARD_CARRIER_CODE, Express::AHAMOVE_EXPRESS_CARRIER_CODE])) {
            return "ahamove";
        }

        return parent::getModuleName();
    }

    /**
     * {@inheritDoc}
     */
    public function getTrackURL()
    {
        $trackingInfo = $this->getTrackingInfo();
        $listShippingMethod = [
            Standard::AHAMOVE_STANDARD_CARRIER_CODE,
            Express::AHAMOVE_EXPRESS_CARRIER_CODE
        ];
        if (in_array($trackingInfo['shipping_method'], $listShippingMethod)) {
            $params = [
                'order_id' => $trackingInfo['service_order_id'],
                'token' => $this->config->getToken(),
            ];
            $response = $this->api->name('Detail Order Ahamove')
                    ->withContentType('application/x-www-form-urlencoded')
                    ->to(\Secomm\Ahamove\Model\Config::URL_AHAMOVE_SHARED_LINK)
                    ->withData($params)
                    ->asJsonResponse(true)
                    ->get();

            if ($response->status == \Secomm\Ahamove\Model\Config\Source\ApiRequest\Status::STATUS_CODE_SUCCESS) {
                return $response->content['shared_link'] ?? "";
            }
        }

        return parent::getTrackURL();
    }
}