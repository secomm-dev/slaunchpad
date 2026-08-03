<?php
/**
 * Reads fields from MoMo gateway command results.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Helper;

use Magento\Framework\Exception\LocalizedException;

class TransactionReader
{
    /**
     * Read the payUrl returned by the create-order command.
     *
     * @param array $transactionResult
     * @return string
     * @throws LocalizedException
     */
    public static function readPayUrl(array $transactionResult): string
    {
        if (empty($transactionResult['payUrl'])) {
            throw new LocalizedException(__('MoMo did not return a payment URL.'));
        }

        return (string)$transactionResult['payUrl'];
    }
}
