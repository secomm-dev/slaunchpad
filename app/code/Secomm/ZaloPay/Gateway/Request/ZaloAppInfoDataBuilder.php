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
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Store\Model\StoreManagerInterface;

class ZaloAppInfoDataBuilder extends AbstractDataBuilder implements BuilderInterface
{
    /**
     * ZaloAppInfoDataBuilder constructor.
     * @param ConfigInterface $config
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly DateTime        $dateTime
    ) {
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $orderIncrementId = $payment->getOrder()->getOrderIncrementId();

        return [
            self::APP_ID => $this->getConfig(self::APP_ID),
            self::APP_TIME => $this->dateTime->timestamp() * 1000,
            self::APP_TRANS_ID => $this->getAppTransId($orderIncrementId),
            self::APP_USER => $this->getConfig(self::APP_USER)
        ];
    }

    /**
     * @param $orderIncrementId
     * @return string
     */
    private function getAppTransId($orderIncrementId): string
    {
        $timestamp = $this->dateTime->timestamp() * 1000;
        return $this->dateTime->gmtDate('ymd') . "_" . $timestamp . '_' . $orderIncrementId;
    }

    /**
     * @return string
     */
    private function getExtraData(): string
    {
        return 'merchantName=' . $this->config->getValue('merchant_name');
    }

    /**
     * Get Config
     *
     * @param $path
     * @return mixed
     */
    private function getConfig($path): mixed
    {
        return $this->config->getValue($path);
    }
}
