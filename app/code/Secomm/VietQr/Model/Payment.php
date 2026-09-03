<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Model;

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * VietQR offline payment method model (TASK-N35E28 / SPEC-FEAT-ZKD4VA §3 AC-005, AC-007).
 *
 * An offline payment method that displays a VietQR code for the customer
 * to scan and complete their bank transfer. No online capture or refund.
 */
class Payment extends AbstractMethod
{
    public const CODE = 'secomm_vietqr';

    protected $_code = self::CODE;
    protected $_isOffline = true;
    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = false;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;

    /**
     * @return string|null
     */
    public function getConfigPaymentAction(): ?string
    {
        return null;
    }

    /**
     * Get payment instructions shown in checkout.
     */
    public function getInstructions(): string
    {
        $instructions = $this->getConfigData('payment_instructions');

        return $instructions !== null ? trim($instructions) : '';
    }
}
