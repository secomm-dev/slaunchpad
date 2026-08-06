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

namespace Secomm\ZaloPay\Block;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;

class Info extends ConfigurableInfo
{
    /**
     * Returns label
     *
     * @param string $field
     * @return Phrase
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function getLabel($field): Phrase
    {
        return match ($field) {
            'transaction_type' => __('Transaction Type'),
            'transaction_id' => __('Transaction ID'),
            'response_code' => __('Response Code'),
            'approve_messages' => __('Approve Messages'),
            default => parent::getLabel($field),
        };
    }
}
