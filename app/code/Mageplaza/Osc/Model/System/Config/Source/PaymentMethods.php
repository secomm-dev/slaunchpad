<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Osc
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Osc\Model\System\Config\Source;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Option\ArrayInterface;
use Mageplaza\Osc\Helper\Data as OscHelper;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * Class PaymentMethods
 * @package Mageplaza\Osc\Model\System\Config\Source
 */
class PaymentMethods implements ArrayInterface
{
    /**
     * @var Factory
     */
    protected $_paymentMethodFactory;

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var OscHelper
     */
    protected $_oscHelper;

    /**
     * PaymentMethods constructor.
     *
     * @param OscHelper $oscHelper
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(
        OscHelper $oscHelper,
        PaymentHelper $paymentHelper
    ) {
        $this->_oscHelper = $oscHelper;
        $this->_paymentHelper = $paymentHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $options = [['label' => __('No'), 'value' => '']];

        $payments = $this->_paymentHelper->getPaymentMethods();
        
        foreach ($payments as $paymentCode => $paymentModel) {
            if (strpos($paymentCode, 'payment_services') !== false) {
                if (isset($paymentModel['can_use_checkout']) && $paymentModel['can_use_checkout'] == 1) {
                    $options[$paymentCode] = [
                        'label' => $paymentModel['title'],
                        'value' => $paymentCode
                    ];
                }
            }
            if (!isset($paymentModel['active']) || $paymentModel['active'] == 0) {
                continue;
            }
            if ($paymentCode !== 'free' && isset($paymentModel['title'])) {
                $options[$paymentCode] = [
                    'label' => $paymentModel['title'],
                    'value' => $paymentCode
                ];
            }
        }

        return $options;
    }
}
