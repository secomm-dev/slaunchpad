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

use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;

class TransactionReader
{
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
}
