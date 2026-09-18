<?php
/**
 * Builds the MoMo create-order (/v2/gateway/api/create) payload from the
 * persisted payment attempt (MOMO-01 payment-first).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Model\Config;

/**
 * Attempt-based create-order payload: orderId = order_ref, requestId =
 * attempt request_id, extraData = base64(order_ref), amount = the attempt's
 * frozen VND snapshot (NOT a live order/quote total — no Sales Order exists
 * yet). rawSignature field order is MoMo's documented create-order order.
 */
class CreateOrderBuilder implements BuilderInterface
{
    /**
     * CreateOrderBuilder constructor.
     *
     * @param Config $config
     * @param Signature $signature
     */
    public function __construct(
        private readonly Config $config,
        private readonly Signature $signature
    ) {
    }

    /**
     * Build the MoMo create-order payload from the persisted attempt.
     *
     * @param array $buildSubject expects: attempt (PaymentAttemptInterface),
     *        payment (payment data object), amount (float VND).
     * @return array<string, scalar>
     * @throws \InvalidArgumentException When the attempt (or its minted
     *         references) is missing.
     */
    public function build(array $buildSubject): array
    {
        $attempt = $buildSubject['attempt'] ?? null;
        if (!$attempt instanceof PaymentAttemptInterface
            || (string)$attempt->getOrderRef() === ''
            || (string)$attempt->getRequestId() === ''
        ) {
            throw new \InvalidArgumentException(
                'A persisted attempt with order_ref and request_id is required to build the MoMo create request.'
            );
        }
        $orderRef = (string)$attempt->getOrderRef();
        $requestId = (string)$attempt->getRequestId();
        // The FROZEN attempt amount — never a live total.
        $amount = (int)$attempt->getAmount();
        SubjectReader::readPayment($buildSubject); // structural: payment DO present (adapter contract)
        // Ties the IPN/Return payload back to THIS attempt even when only
        // orderId is spoofable.
        $extraData = base64_encode($orderRef);

        // rawSignature MUST follow MoMo's exact create-order field order.
        // Text values are URL-encoded so reserved characters (&, =) cannot
        // alter the signature.
        $orderInfo = (string)__('Payment for order %1', $orderRef);
        $rawParams = [
            'accessKey' => $this->config->getAccessKey(),
            'amount' => $amount,
            'extraData' => $extraData,
            'ipnUrl' => $this->config->getNotifyUrl(),
            'orderId' => $orderRef,
            'orderInfo' => rawurlencode($orderInfo),
            'partnerCode' => $this->config->getPartnerCode(),
            'redirectUrl' => $this->config->getReturnUrl(),
            'requestId' => $requestId,
            'requestType' => Config::KEY_REQUEST_TYPE,
        ];

        return [
            'partnerCode' => $this->config->getPartnerCode(),
            'accessKey' => $this->config->getAccessKey(),
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderRef,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $this->config->getReturnUrl(),
            'ipnUrl' => $this->config->getNotifyUrl(),
            'extraData' => $extraData,
            'requestType' => Config::KEY_REQUEST_TYPE,
            'signature' => $this->signature->sign($rawParams, $this->config->getSecretKey()),
            'lang' => 'vi',
        ];
    }
}
