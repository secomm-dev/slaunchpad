<?php

declare(strict_types=1);

namespace Secomm\VietQr\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Secomm\VietQr\Api\QrGeneratorInterface;
use Secomm\VietQr\Model\Config;

/**
 * Generates a VietQR code after an order is placed with the VietQR payment
 * method and stores it in the payment additional information.
 *
 * The observer is idempotent — it skips the API call if a QR code already
 * exists. Failures are logged but never thrown (the order is already saved).
 */
class GenerateQrAfterOrder implements ObserverInterface
{
    public function __construct(
        private readonly QrGeneratorInterface $qrGenerator,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getData('order');
        if (!$order instanceof OrderInterface) {
            return;
        }

        $payment = $order->getPayment();
        if (!$payment || $payment->getMethod() !== 'secomm_vietqr') {
            return;
        }

        // Idempotency: do not call API if QR already generated
        if ($payment->getAdditionalInformation('vietqr_qr_code')) {
            return;
        }

        try {
            $result = $this->qrGenerator->generate($order);
            $now = (new \DateTime())->format('Y-m-d H:i:s');
            $content = str_replace(
                '{{order_increment_id}}',
                $order->getIncrementId(),
                $this->config->getTransferContentTemplate()
            );

            $payment->setAdditionalInformation(array_merge(
                $payment->getAdditionalInformation() ?: [],
                [
                    'vietqr_qr_code' => $result->getQrCode(),
                    'vietqr_bank_code' => $this->config->getBankCode(),
                    'vietqr_bank_name' => $this->config->getBankCode(),
                    'vietqr_bank_account' => $this->config->getBankAccount(),
                    'vietqr_account_name' => $this->config->getAccountName(),
                    'vietqr_amount' => (float)$order->getGrandTotal(),
                    'vietqr_content' => $content,
                    'vietqr_generated_at' => $now,
                ]
            ));
            $payment->save();
        } catch (\Throwable $e) {
            // Order already persisted — do not throw, do not rollback
            $this->logger->warning(
                'VietQR QR generation failed for order ' . $order->getIncrementId(),
                ['error' => $e->getMessage()]
            );
        }
    }
}
