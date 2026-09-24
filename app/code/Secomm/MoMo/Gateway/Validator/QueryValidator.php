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
use Secomm\MoMo\Model\Config;

/**
 * Structural + identity validation of the MoMo v2/query response
 * (MOMO-01). MoMo's Check Transaction Status API signs the REQUEST but
 * returns NO response signature — the merchant-initiated HTTPS query IS
 * the server-side verification channel. The validation subject carries:
 * `response` (raw query response), `query_request` (the exact request this
 * command just sent) and `attempt` (the persisted attempt).
 *
 * Checks:
 *  1. partnerCode is OUR partner code;
 *  2. orderId echo == the query request's orderId == attempt.order_ref;
 *  3. requestId echo == the FRESH query requestId this command just sent
 *     (never the attempt's create-time request_id);
 *  4. amount is present and strictly integer-formed (provider realism).
 *
 * Business decisions — resultCode mapping, amount comparison against the
 * attempt snapshot, positive-transId requirement — belong to the caller
 * (ReturnProcessor).
 */
class QueryValidator extends AbstractValidator
{
    /**
     * QueryValidator constructor.
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param Config $config
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly Config $config
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * Validate the raw query response against the exact query request and
     * the persisted attempt.
     *
     * @param array $validationSubject expects: response, query_request, attempt.
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = (array)($validationSubject['response'] ?? []);
        $queryRequest = (array)($validationSubject['query_request'] ?? []);
        $attempt = $validationSubject['attempt'] ?? null;
        if (!$attempt instanceof PaymentAttemptInterface) {
            return $this->createResult(false, [__('MoMo payment attempt is required for query validation.')]);
        }
        $errors = [];

        if (($queryRequest['requestId'] ?? '') === '') {
            $errors[] = __('MoMo query request is missing its requestId.');
        }

        if ((string)($response['partnerCode'] ?? '') !== $this->config->getPartnerCode()) {
            $errors[] = __('MoMo partnerCode mismatch.');
        }

        if ((string)($response['orderId'] ?? '') !== (string)($queryRequest['orderId'] ?? '')
            || (string)($queryRequest['orderId'] ?? '') !== (string)$attempt->getOrderRef()
        ) {
            $errors[] = __('MoMo orderId does not match the payment attempt.');
        }
        if ((string)($response['requestId'] ?? '') !== (string)($queryRequest['requestId'] ?? '')) {
            $errors[] = __('MoMo query requestId echo does not match the query request.');
        }

        $amount = $response['amount'] ?? '';
        if (!is_scalar($amount) || preg_match('/^\d+$/', (string)$amount) !== 1) {
            $errors[] = __('MoMo query response amount is malformed.');
        }

        return $this->createResult(empty($errors), $errors);
    }
}
