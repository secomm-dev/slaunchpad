<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Request;

use Secomm\ZaloPay\Gateway\Helper\Rate;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class OrderAdditionalInformationDataBuilder extends AbstractDataBuilder implements BuilderInterface
{
    /**
     * Zalo Pay App
     */
    const ZALOPAY_APP = 'zalopayapp';

    /**
     * Desc Text
     */
    const DESCRIPTION_TEXT = 'ZaloPay Integration for Magento 2';

    /**
     * OrderAdditionalInformationDataBuilder constructor.
     *
     * @param Json $serializer
     * @param Rate $helperRate
     * @param ConfigInterface $config
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly Json     $serializer,
        private readonly Rate     $helperRate,
        protected ConfigInterface $config,
        protected UrlInterface    $url
    ) {
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $order = $payment->getPayment()->getOrder();
        $incrementId = $order->getIncrementId();
        $form = $this->config->getValue('form');
        $embed = $this->getEmbedData();
        $bankCode = '';
        $embed['preferred_payment_method'] = match ($form) {
            'domestic_card_account' => ["domestic_card", "account"],
            'zalopay_wallet' => ["zalopay_wallet"],
            'vietqr' => ["vietqr"],
            'international_card' => ["international_card"],
            'applepay' => ["applepay"],
            default => [],
        };
        return [
            self::EMBED_DATA => $this->serializer->serialize($embed),
            self::AMOUNT => (int)$this->helperRate->getVndAmount($order, round((float)SubjectReader::readAmount($buildSubject), 2)),
            self::DESCRIPTION => $this->getDesc() . " #$incrementId",
            self::BANK_CODE => $bankCode,
            self::CALL_BACK => $this->getCallBackUrl()
        ];
    }

    /**
     * @return array
     */
    private function getEmbedData(): array
    {
        return [
            self::MERCHANT_INFO => $this->getAppUser(),
            self::REDIRECT_URL => $this->getRedirectUrl(),
            'preferred_payment_method' => [
            ]
        ];
    }

    public function getRedirectUrl()
    {
        $baseURL = $this->url->getBaseUrl();
        $controllerAction = 'zalopay/payment/returnaction';
        return $baseURL . $controllerAction;
    }

    public function getCallBackUrl()
    {
        $baseURL = $this->url->getBaseUrl();
        $controllerAction = 'zalopay/payment/ipn';
        return $baseURL . $controllerAction;
    }

    /**
     * @return string
     */
    private function getDesc(): string
    {
        $result = $this->config->getValue('desc');
        if ($result == '') {
            return 'Secomm';
        }
        return $result;
    }

    /**
     * @return string
     */
    private function getAppUser(): string
    {
        $result = $this->config->getValue('appuser');
        if ($result == '') {
            return 'Secomm';
        }
        return $result;
    }
}
