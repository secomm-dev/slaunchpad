<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * Exposes VietQR payment instructions to the checkout JS config
 * (TASK-N35E28 / SPEC-FEAT-ZKD4VA §3 AC-005).
 *
 * Populates window.checkoutConfig.payment.instructions.secomm_vietqr.
 */
class InstructionsConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly PaymentHelper $paymentHelper,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        $method = $this->paymentHelper->getMethodInstance(Payment::CODE);

        if (!$method->isAvailable()) {
            return [];
        }

        $instructions = trim((string)$method->getInstructions());
        if ($instructions === '') {
            return [];
        }

        return [
            'payment' => [
                'instructions' => [
                    Payment::CODE => nl2br($this->escaper->escapeHtml($instructions)),
                ],
            ],
        ];
    }
}
