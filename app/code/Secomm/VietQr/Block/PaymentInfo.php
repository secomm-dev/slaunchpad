<?php

declare(strict_types=1);

namespace Secomm\VietQr\Block;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Secomm\VietQr\Api\QrGeneratorInterface;
use Secomm\VietQr\Model\Config;

/**
 * Renders the VietQR payment information page with QR code, bank details,
 * and transfer instructions for the customer to complete their payment.
 *
 * This block handles ownership verification, QR code generation (with a
 * retry mechanism), and provides all the data needed for the payment view
 * and confirmation form.
 */
class PaymentInfo extends \Magento\Framework\View\Element\Template
{
    private ?OrderInterface $order = null;
    private ?array $paymentInfo = null;

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly UrlInterface $urlBuilder,
        private readonly Config $config,
        private readonly QrGeneratorInterface $qrGenerator,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Initialise the block for a given order after verifying ownership.
     *
     * @param int $orderId
     * @param string|null $key Guest protect code for ownership verification
     * @throws NoSuchEntityException When the order is not found or the customer does not own it
     */
    public function init(int $orderId, ?string $key = null): void
    {
        $this->order = $this->orderRepository->get($orderId);
        $this->verifyOwnership($key);
        $this->loadPaymentInfo();
    }

    /**
     * @return OrderInterface|null
     */
    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    /**
     * @return string
     */
    public function getOrderIncrementId(): string
    {
        return $this->order?->getIncrementId() ?? '';
    }

    /**
     * @return string
     */
    public function getQrCode(): string
    {
        return $this->paymentInfo['vietqr_qr_code'] ?? '';
    }

    /**
     * Render the QR code as an SVG string.
     *
     * @param int $size Width/height of the QR code in pixels
     * @return string SVG markup, or empty string on failure
     */
    public function getQrSvg(int $size = 256): string
    {
        $qrCode = $this->getQrCode();
        if (!$qrCode) {
            return '';
        }

        try {
            $renderer = new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $svg = $writer->writeString($qrCode);
            return preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $svg) ?? $svg;
        } catch (\Throwable $e) {
            $this->logger->error(
                'VietQR SVG generation failed: ' . $e->getMessage(),
                ['order_id' => $this->order?->getId()]
            );
            return '';
        }
    }

    /**
     * @return bool
     */
    public function isQrAvailable(): bool
    {
        return !empty($this->paymentInfo['vietqr_qr_code']);
    }

    /**
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return (bool)($this->paymentInfo['vietqr_customer_confirmed'] ?? false);
    }

    /**
     * @return string
     */
    public function getBankCode(): string
    {
        return $this->paymentInfo['vietqr_bank_code'] ?? '';
    }

    /**
     * @return string
     */
    public function getBankAccount(): string
    {
        return $this->paymentInfo['vietqr_bank_account'] ?? '';
    }

    /**
     * @return string
     */
    public function getAccountName(): string
    {
        return $this->paymentInfo['vietqr_account_name'] ?? '';
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return (float)($this->paymentInfo['vietqr_amount'] ?? $this->order?->getGrandTotal() ?? 0);
    }

    /**
     * @return string
     */
    public function getFormattedAmount(): string
    {
        if (!$this->order || !$this->order->getOrderCurrency()) {
            return '';
        }
        return $this->order->getOrderCurrency()->formatTxt($this->getAmount());
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->paymentInfo['vietqr_content'] ?? '';
    }

    /**
     * @return string
     */
    public function getInstructions(): string
    {
        return $this->paymentInfo['vietqr_payment_instructions'] ?? $this->config->getPaymentInstructions();
    }

    /**
     * Get the cancel/back URL, context-aware based on where the user came from.
     *
     * @return string
     */
    public function getCancelUrl(): string
    {
        if (!$this->order) {
            return $this->urlBuilder->getUrl('/');
        }

        $from = $this->getRequest()->getParam('from');
        if ($from === 'order') {
            return $this->getOrderViewUrl();
        }

        $lastOrderId = (int)$this->checkoutSession->getLastOrderId();
        $isCurrentCheckoutOrder = $lastOrderId === (int)$this->order->getId();

        if ($isCurrentCheckoutOrder) {
            return $this->urlBuilder->getUrl('checkout/onepage/success', ['skip_vietqr' => 1]);
        }

        return $this->getOrderViewUrl();
    }

    /**
     * @return string
     */
    public function getOrderViewUrl(): string
    {
        if (!$this->order) {
            return $this->urlBuilder->getUrl('/');
        }

        $params = ['order_id' => $this->order->getId()];
        if ((bool)$this->order->getCustomerIsGuest()) {
            $params['key'] = $this->order->getProtectCode();
        }

        return $this->urlBuilder->getUrl('sales/order/view', $params);
    }

    /**
     * @return string
     */
    public function getFormActionUrl(): string
    {
        $params = ['order_id' => $this->order?->getId()];
        if ($from = $this->getRequest()->getParam('from')) {
            $params['from'] = $from;
        }
        if ($this->order && (bool)$this->order->getCustomerIsGuest()) {
            $params['key'] = $this->order->getProtectCode();
        }
        return $this->urlBuilder->getUrl('vietqr/payment/submit', $params);
    }

    /**
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->getBlockHtml('formkey');
    }

    /**
     * Verify that the current customer owns the order being viewed.
     *
     * For registered customers the order's customer ID must match the session
     * customer. For guests the protect code passed via URL must match the order.
     *
     * @param string|null $key Guest protect code
     * @throws NoSuchEntityException When ownership cannot be verified
     */
    private function verifyOwnership(?string $key): void
    {
        if (!$this->order) {
            throw new NoSuchEntityException(__('Order not found.'));
        }

        $payment = $this->order->getPayment();
        if (!$payment || $payment->getMethod() !== 'secomm_vietqr') {
            throw new NoSuchEntityException(__('Invalid order.'));
        }

        $isGuest = (bool)$this->order->getCustomerIsGuest();

        if (!$isGuest) {
            $customerId = $this->customerSession->getCustomerId();
            if (!$customerId || $this->order->getCustomerId() != $customerId) {
                throw new NoSuchEntityException(__('Order not found.'));
            }
            return;
        }

        // Guest: verify via protected_key
        if ($key === null || $key !== $this->order->getProtectCode()) {
            throw new NoSuchEntityException(__('Order not found.'));
        }
    }

    /**
     * Load payment information from the order payment, retrying QR generation
     * once if the code was not generated during order placement.
     */
    private function loadPaymentInfo(): void
    {
        $payment = $this->order->getPayment();
        $this->paymentInfo = $payment ? $payment->getAdditionalInformation() : [];

        // Retry QR generation once if API failed during order placement
        if (empty($this->paymentInfo['vietqr_qr_code'])) {
            try {
                $result = $this->qrGenerator->generate($this->order);
                $now = (new \DateTime())->format('Y-m-d H:i:s');
                $content = str_replace(
                    '{{order_increment_id}}',
                    $this->order->getIncrementId(),
                    $this->config->getTransferContentTemplate()
                );

                $newInfo = array_merge($this->paymentInfo ?: [], [
                    'vietqr_qr_code' => $result->getQrCode(),
                    'vietqr_bank_code' => $this->config->getBankCode(),
                    'vietqr_bank_name' => $this->config->getBankCode(),
                    'vietqr_bank_account' => $this->config->getBankAccount(),
                    'vietqr_account_name' => $this->config->getAccountName(),
                    'vietqr_amount' => (float)$this->order->getGrandTotal(),
                    'vietqr_content' => $content,
                    'vietqr_generated_at' => $now,
                ]);
                $payment->setAdditionalInformation($newInfo);
                $payment->save();
                $this->paymentInfo = $newInfo;
            } catch (\Throwable $e) {
                $this->logger->warning(
                    'VietQR QR retry failed for order ' . $this->order->getIncrementId(),
                    ['error' => $e->getMessage()]
                );
            }
        }
    }
}
