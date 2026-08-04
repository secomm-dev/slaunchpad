<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model\Config\Source;

class PaymentsForm implements \Magento\Framework\Data\OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('The payment via ZaloPay gateway')],
            ['value' => 'domestic_card_account', 'label' => __('ATM/Internet Banking payment through ZaloPay')],
            ['value' => 'zalopay_wallet', 'label' => __('ZaloPay QR code for payment using Zalo/ZaloPay')],
            ['value' => 'vietqr', 'label' => __('Payment via VietQR')],
            ['value' => 'international_card', 'label' => __('Visa, Master, JCB')],
            ['value' => 'applepay', 'label' => __('Apple Pay')],
        ];
    }
}
