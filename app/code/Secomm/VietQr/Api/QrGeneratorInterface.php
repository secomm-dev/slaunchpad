<?php

declare(strict_types=1);

namespace Secomm\VietQr\Api;

use Magento\Sales\Api\Data\OrderInterface;
use Secomm\VietQr\Model\VietQr\QrResult;

/**
 * Generates a VietQR code for a given order.
 *
 * Implementations communicate with the VietQR API to produce a QR code
 * that customers can scan to complete their bank transfer.
 */
interface QrGeneratorInterface
{
    /**
     * Generate a VietQR code for the given order.
     *
     * @param OrderInterface $order
     * @return QrResult
     */
    public function generate(OrderInterface $order): QrResult;
}
