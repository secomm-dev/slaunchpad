<?php

namespace Boolfly\GiaoHangNhanh\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Payment\Helper\Data as PaymentData;

class PaymentShippingCOD implements OptionSourceInterface
{
    protected $paymentHelper;

    public function __construct(
        PaymentData $paymentHelper
    ) {
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        $options = [];
        $allPaymentMethodsArray = $this->paymentHelper->getPaymentMethodList();
        foreach ($allPaymentMethodsArray as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label
            ];
        }

        return $options;
    }
}
