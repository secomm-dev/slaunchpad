<?php declare(strict_types=1);

namespace Secomm\Ahamove\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\Ahamove\Model\Config;

class PaymentType implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => Config::PAYMENT_CASH, 'label' => __(Config::PAYMENT_CASH_DESCRIPTION)],
//            ['value' => Config::PAYMENT_CASH_BY_RECIPIENT, 'label' => __(Config::PAYMENT_CASH_BY_RECIPIENT_DESCRIPTION)],
            ['value' => Config::PAYMENT_BALANCE, 'label' => __(Config::PAYMENT_BALANCE_DESCRIPTION)]
        ];

        return $options;
    }
}
