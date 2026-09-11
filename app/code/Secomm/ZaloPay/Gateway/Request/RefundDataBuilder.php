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
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Store\Model\App\Emulation;

/**
 * Class TransactionIdDataBuilder
 *
 */
class RefundDataBuilder extends AbstractDataBuilder implements BuilderInterface
{
    /**
     * RefundDataBuilder constructor.
     *
     * @param ConfigInterface $config
     * @param DateTime $dateTime
     * @param Rate $helperRate
     * @param Emulation $appEmulation
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly DateTime        $dateTime,
        private readonly Rate            $helperRate,
        private readonly Emulation       $appEmulation
    ) {
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $amount = round((float)SubjectReader::readAmount($buildSubject), 2);
        $payment = $paymentDO->getPayment();
        $timestamp = $this->dateTime->timestamp() * 1000;
        $uid = $timestamp . rand(111, 999);
        $appId = $this->config->getValue(self::APP_ID);
        $storeId = (int)$paymentDO->getOrder()->getStoreId();

        $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            $description = (string)__(
                'Refund for order #%1',
                $paymentDO->getOrder()->getOrderIncrementId()
            );
        } finally {
            $this->appEmulation->stopEnvironmentEmulation();
        }

        return [
            self::APP_ID => $appId,
            self::M_REFUND_ID => $this->dateTime->gmtDate('ymd') . '_' . $appId . '_' . $uid,
            self::TIMESTAMP => $timestamp,
            self::ZP_TRANS_ID => $payment->getParentTransactionId(),
            self::AMOUNT => (int)$this->helperRate->getVndAmount($payment->getOrder(), $amount),
            self::DESCRIPTION => $description,
        ];
    }
}
