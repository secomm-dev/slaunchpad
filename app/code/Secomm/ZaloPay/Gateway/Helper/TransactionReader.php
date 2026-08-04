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

namespace Secomm\ZaloPay\Gateway\Helper;

use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;

class TransactionReader
{
    /**
     * Is IPN request
     */
    const IS_IPN = 'is_ipn';

    /**
     * Read Pay Url from transaction data
     *
     * @param array $transactionData
     * @return string
     */
    public static function readPayUrl(array $transactionData): string
    {
        if (empty($transactionData[AbstractResponseValidator::PAY_URL])) {
            throw new \InvalidArgumentException('Pay Url should be provided');
        }

        return $transactionData[AbstractResponseValidator::PAY_URL];
    }

    /**
     * Read Order Id from transaction data
     *
     * @param array $transactionData
     * @return string
     */
    public static function readOrderId(array $transactionData): string
    {
        if (empty($transactionData[AbstractDataBuilder::TRANS_DATA][AbstractDataBuilder::APP_TRANS_ID])) {
            throw new \InvalidArgumentException('Order Id doesn\'t exist');
        }

        return explode('_', $transactionData[AbstractDataBuilder::TRANS_DATA][AbstractDataBuilder::APP_TRANS_ID])[2];
    }

    /**
     * Check Is IPN from transaction data
     *
     * @param array $transactionData
     * @return bool
     */
    public static function isIpn(array $transactionData): bool
    {
        if (!empty($transactionData[self::IS_IPN]) && $transactionData[self::IS_IPN]) {
            return true;
        }

        return false;
    }
}
