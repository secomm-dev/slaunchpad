<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Model\VietQr;

use Magento\Sales\Api\Data\OrderInterface;
use Secomm\VietQr\Model\Config;
use Secomm\VietQr\Model\VndAmount;

/**
 * Builds the request payload for the VietQR API from an order and config
 * (TASK-N35E28 / SPEC-FEAT-ZKD4VA §3 AC-008).
 *
 * Translates order data (increment ID, grand total converted to VND) and
 * bank configuration into the array structure expected by the VietQR API.
 */
class RequestBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly VndAmount $vndAmount
    ) {
    }

    /**
     * @param OrderInterface $order
     * @return array
     */
    public function build(OrderInterface $order): array
    {
        $content = str_replace(
            '{{order_increment_id}}',
            $order->getIncrementId(),
            $this->config->getTransferContentTemplate()
        );

        return [
            'bankAccount' => $this->config->getBankAccount(),
            'userBankName' => $this->config->getAccountName(),
            'bankCode' => $this->config->getBankCode(),
            'amount' => $this->vndAmount->get($order),
            'content' => $content,
        ];
    }
}
