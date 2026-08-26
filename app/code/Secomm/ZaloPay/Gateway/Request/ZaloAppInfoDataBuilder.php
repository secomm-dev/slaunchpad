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

use Secomm\ZaloPay\Model\AppTransIdBuilder;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;

class ZaloAppInfoDataBuilder extends AbstractDataBuilder implements BuilderInterface
{
    /**
     * ZaloAppInfoDataBuilder constructor.
     * @param ConfigInterface $config
     * @param AppTransIdBuilder $appTransIdBuilder
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly AppTransIdBuilder $appTransIdBuilder
    ) {
    }

    /**
     * Works for BOTH flows: the PaymentDataObject order adapter exposes
     * getOrderIncrementId() on sales orders (increment id) and on quotes
     * (reserved order id) alike. The payment-first initiation passes the
     * app_trans_id it already persisted (it is the unique attempt key) —
     * only the legacy order-first flow mints a fresh one here.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $orderIncrementId = $payment->getOrder()->getOrderIncrementId();
        $appTransId = (string)($buildSubject[self::APP_TRANS_ID] ?? '');

        return [
            self::APP_ID => $this->getConfig(self::APP_ID),
            self::APP_TIME => $this->appTransIdBuilder->getAppTime(),
            self::APP_TRANS_ID => $appTransId !== '' ? $appTransId : $this->appTransIdBuilder->build($orderIncrementId),
            self::APP_USER => $this->getConfig(self::APP_USER)
        ];
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
