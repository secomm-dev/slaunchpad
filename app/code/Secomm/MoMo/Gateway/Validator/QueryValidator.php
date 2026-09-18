<?php
/**
 * Validates the MoMo v2/query (transaction status) response.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Model\Config;

/**
 * Structural + identity validation of the MoMo v2/query response
 * (MOMO-01). The validation subject carries the raw query response under
 * `response` and the resolved attempt under `attempt`.
 *
 * Checks:
 *  1. signature over MoMo's query-response field order
 *     (accessKey&amount&message&orderId&partnerCode&responseTime);
 *  2. partnerCode is OUR partner code;
 *  3. transaction identity echoes: orderId == attempt.order_ref,
 *     requestId == attempt.request_id.
 *
 * Business decisions — resultCode mapping, amount comparison against the
 * attempt snapshot, transId capture — belong to the caller (ReturnProcessor).
 */
class QueryValidator extends AbstractValidator
{
    /**
     * Query response fields MoMo signs (fixed order per MoMo v2 docs).
     */
    private const SIGNED_FIELDS = [
        'accessKey',
        'amount',
        'message',
        'orderId',
        'partnerCode',
        'responseTime',
    ];

    /**
     * QueryValidator constructor.
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param Config $config
     * @param Signature $signature
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly Config $config,
        private readonly Signature $signature
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * Validate the raw query response against the persisted attempt.
     *
     * @param array $validationSubject expects: response (raw query fields), attempt.
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = (array)($validationSubject['response'] ?? []);
        $attempt = $validationSubject['attempt'] ?? null;
        if (!$attempt instanceof PaymentAttemptInterface) {
            return $this->createResult(false, [__('MoMo payment attempt is required for query validation.')]);
        }
        $errors = [];

        $signature = (string)($response['signature'] ?? '');
        if ($signature === '' || !$this->verifySignature($signature, $response)) {
            $errors[] = __('MoMo query signature verification failed.');
        }

        if ((string)($response['partnerCode'] ?? '') !== $this->config->getPartnerCode()) {
            $errors[] = __('MoMo partnerCode mismatch.');
        }

        if ((string)($response['orderId'] ?? '') !== (string)$attempt->getOrderRef()) {
            $errors[] = __('MoMo orderId does not match the payment attempt.');
        }
        if ((string)($response['requestId'] ?? '') !== (string)$attempt->getRequestId()) {
            $errors[] = __('MoMo requestId does not match the payment attempt.');
        }

        return $this->createResult(empty($errors), $errors);
    }

    /**
     * Verify the query response signature (fixed order per MoMo v2 docs).
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
