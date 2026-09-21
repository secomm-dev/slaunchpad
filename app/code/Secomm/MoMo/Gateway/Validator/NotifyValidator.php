<?php
/**
 * Validates the MoMo Notify (IPN) payload against the persisted attempt.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Model\Config;

/**
 * Attempt-based IPN validation (MOMO-01). The validation subject carries the
 * raw IPN fields under `response` and the resolved attempt under `attempt`.
 *
 * The full authoritative chain:
 *  1. structural presence of the 13 MoMo-signed fields + signature;
 *  2. HMAC-SHA256 signature over MoMo's fixed 13-field order;
 *  3. merchant identity: partnerCode must be OUR partner code;
 *  4. transaction identity echoes: orderId == attempt.order_ref,
 *     requestId == attempt.request_id;
 *  5. extraData (when present) decodes to the attempt's order_ref;
 *  6. amount == the attempt's frozen VND amount.
 *
 * The business outcome (resultCode == 0 success vs provider failure) is the
 * CALLER's decision (IpnProcessor) — this validator is purely identity +
 * integrity, so a FAILED provider transaction can still be verified as
 * authentic and recorded as a verified failure.
 */
class NotifyValidator extends AbstractValidator
{
    public const RESULT_CODE = 'resultCode';

    /**
     * Result fields MoMo signs (IPN / result rawSignature — fixed order).
     */
    private const SIGNED_FIELDS = [
        'accessKey',
        'amount',
        'extraData',
        'message',
        'orderId',
        'orderInfo',
        'orderType',
        'partnerCode',
        'payType',
        'requestId',
        'responseTime',
        'resultCode',
        'transId',
    ];

    /**
     * NotifyValidator constructor.
     *
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param Config $config
     * @param Signature $signature
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        private readonly Config $config,
        private readonly Signature $signature
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * Validate the raw IPN payload against the persisted attempt.
     *
     * @param array $validationSubject expects: response (raw IPN fields), attempt.
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = (array)($validationSubject['response'] ?? []);
        $attempt = $validationSubject['attempt'] ?? null;
        if (!$attempt instanceof PaymentAttemptInterface) {
            return $this->createResult(false, [__('MoMo payment attempt is required for IPN validation.')]);
        }
        $errors = [];

        $signature = (string)($response['signature'] ?? '');
        if ($signature === '' || !$this->verifySignature($signature, $response)) {
            $errors[] = __('MoMo notify signature verification failed.');
        }

        if ((string)($response['partnerCode'] ?? '') !== $this->config->getPartnerCode()) {
            $errors[] = __('MoMo partnerCode mismatch.');
        }

        // Transaction identity echoes: the provider must answer with the
        // exact merchant references minted for THIS attempt.
        if ((string)($response['orderId'] ?? '') !== (string)$attempt->getOrderRef()) {
            $errors[] = __('MoMo orderId does not match the payment attempt.');
        }
        if ((string)($response['requestId'] ?? '') !== (string)$attempt->getRequestId()) {
            $errors[] = __('MoMo requestId does not match the payment attempt.');
        }

        // extraData binds the payload to the attempt even beyond orderId.
        $extraData = (string)($response['extraData'] ?? '');
        if ($extraData !== '') {
            $decoded = base64_decode($extraData, true);
            if ($decoded === false || $decoded !== (string)$attempt->getOrderRef()) {
                $errors[] = __('MoMo extraData does not match the payment attempt.');
            }
        }

        $notifyAmount = isset($response['amount']) ? (int)$response['amount'] : null;
        if ($notifyAmount === null || $notifyAmount !== (int)$attempt->getAmount()) {
            $errors[] = __(
                'MoMo amount mismatch (expected %1, got %2).',
                (int)$attempt->getAmount(),
                $notifyAmount ?? 'unknown'
            );
        }

        return $this->createResult(empty($errors), $errors);
    }

    /**
     * Verify the MoMo signature over the 13 signed fields (fixed order).
     *
     * @param string $signature
     * @param array $response
     * @return bool
     */
    private function verifySignature(string $signature, array $response): bool
    {
        $params = ['accessKey' => $this->config->getAccessKey()];
        foreach (self::SIGNED_FIELDS as $field) {
            if ($field === 'accessKey') {
                continue;
            }
            $params[$field] = (string)($response[$field] ?? '');
        }

        return $this->signature->verify($signature, $params, $this->config->getSecretKey());
    }
}
