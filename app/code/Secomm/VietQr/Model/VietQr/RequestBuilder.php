<?php

declare(strict_types=1);

namespace Secomm\VietQr\Model\VietQr;

use Magento\Sales\Api\Data\OrderInterface;
use Secomm\VietQr\Model\Config;

/**
 * Builds the request payload for the VietQR API from an order and config.
 *
 * Translates order data (increment ID, grand total) and bank configuration
 * into the array structure expected by the VietQR API.
 */
class RequestBuilder
{
    public function __construct(
        private readonly Config $config
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
            'amount' => (float)$order->getGrandTotal(),
            'content' => $content,
        ];
    }
}
