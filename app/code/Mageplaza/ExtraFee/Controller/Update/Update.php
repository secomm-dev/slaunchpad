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

namespace Mageplaza\ExtraFee\Controller\Update;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Json\Helper\Data as JsonData;
use Magento\Quote\Model\Quote\AddressFactory;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;
use Mageplaza\ExtraFee\Model\Multishipping\ExtraFee;

/**
 * Class Update
 * @package Mageplaza\ExtraFee\Controller\Update
 */
class Update extends Action
{
    /**
     * @var JsonData
     */
    protected $jsonHelper;

    /**
     * @var AddressFactory
     */
    protected $addressFactory;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var ExtraFee
     */
    protected $multishippingExtraFee;

    /**
     * Update constructor.
     *
     * @param Context $context
     * @param JsonData $jsonHelper
     * @param AddressFactory $addressFactory
     * @param Data $helperData
     * @param ExtraFee $multishippingExtraFee
     */
    public function __construct(
        Context $context,
        JsonData $jsonHelper,
        AddressFactory $addressFactory,
        Data $helperData,
        ExtraFee $multishippingExtraFee
    ) {
        $this->jsonHelper            = $jsonHelper;
        $this->addressFactory        = $addressFactory;
        $this->helperData            = $helperData;
        $this->multishippingExtraFee = $multishippingExtraFee;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $params       = $this->getRequest()->getParams();
        $rules        = isset($params['rule']) ? $params['rule'] : [];
        $ruleRequired = isset($params['rule_required']) ? $params['rule_required'] : [];
        $addressId    = $params['address_id'];
        $result       = ['status' => true];

        if (empty($rules) && $ruleRequired) {
            $result['status']  = false;
            $result['message'] = __('Please choose at least one option for each require extra fee');
        }

        if ($ruleRequired) {
            foreach ($ruleRequired as $addressId => $required) {
                foreach ($required as $ruleId) {
                    if (!isset($rules[$addressId][$ruleId])) {
                        $result['status']  = false;
                        $result['message'] = __('Please choose at least one option for each require extra fee');
                    }
                }
            }
        }

        if (isset($result['status']) && $result['status']) {
            try {
                $area           = [
                    DisplayArea::PAYMENT_METHOD,
                    DisplayArea::SHIPPING_METHOD,
                    DisplayArea::CART_SUMMARY
                ];
                $quote          = $this->helperData->getCheckoutSession()->getQuote();
                $billingAddress = $quote->getBillingAddress();
                if ((int) $addressId === (int) $billingAddress->getId()) {
                    $extraFeeSummary = isset($rules[$addressId]) ? $rules[$addressId] : [];
                    $this->helperData->setMpExtraFee(
                        $billingAddress,
                        http_build_query(['rule' => $extraFeeSummary]),
                        DisplayArea::CART_SUMMARY
                    );
                    $this->multishippingExtraFee->fetch($billingAddress, implode(',', $area));
                }
                foreach ($quote->getAllShippingAddresses() as $address) {
                    if ((int) $addressId === (int) $address->getId()) {
                        $extraFeeSummary = isset($rules[$addressId]) ? $rules[$addressId] : [];
                        $this->helperData->setMpExtraFee(
                            $address,
                            http_build_query(['rule' => $extraFeeSummary]),
                            DisplayArea::CART_SUMMARY
                        );
                        $this->multishippingExtraFee->fetch($address, implode(',', $area));
                    }
                }
            } catch (Exception $e) {
                $result['status']  = false;
                $result['message'] = $e->getMessage();
            }
        }

        $checkoutSession = $this->helperData->getCheckoutSession();
        $extraFeeNote    = $checkoutSession->getExtraFeeMultiNote() ?: [];

        foreach ($params as $key => $value) {
            if (str_contains($key, 'mp-extrafee-note')) {
                $addressId = array_last(explode('-', $key));
                $extraFeeNote[$addressId][$key] = $value;
            }
        }

        $checkoutSession->setExtraFeeMultiNote($extraFeeNote);

        return $this->getResponse()->representJson($this->jsonHelper->jsonEncode($result));
    }
}
