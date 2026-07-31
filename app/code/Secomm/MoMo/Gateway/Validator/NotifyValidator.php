<?php
/**
 * Validates the MoMo Notify (IPN) payload.
 *
 * Verifies the MoMo signature over the result fields and that resultCode == 0
 * (success). This is the authoritative confirmation.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Secomm\MoMo\Gateway\Config\Config;
use Secomm\MoMo\Gateway\Helper\Signature;

class NotifyValidator extends AbstractValidator
{
    public const RESULT_CODE = 'resultCode';
    public const SUCCESS = 0;

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
     * @var Config
     */
    private Config $config;

    /**
     * @var Signature
     */
    private Signature $signature;

    /**
     * Constructor
     *
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param Config $config
     * @param Signature $signature
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        Config $config,
        Signature $signature
    ) {
        parent::__construct($resultFactory);
        $this->config = $config;
        $this->signature = $signature;
    }

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);
        $errors = [];

        $signature = (string)($response['signature'] ?? '');
        if (!$this->verifySignature($signature, $response)) {
            $errors[] = __('MoMo notify signature verification failed.');
        }

        $resultCode = isset($response[self::RESULT_CODE]) ? (int)$response[self::RESULT_CODE] : null;
        if ($resultCode !== self::SUCCESS) {
            $errors[] = __('MoMo payment not successful (resultCode: %1).', [$resultCode ?? 'unknown']);
        }

        // Spec §10 BLOCK: re-validate amount server-side against the order.
        // The Notify controller asserts the order id before delegating, and the
        // payment data object carries the authorized grand total.
        $paymentDO = SubjectReader::readPayment($validationSubject);
        $order = $paymentDO->getOrder();
        $expectedAmount = (int)round((float)$order->getGrandTotalAmount());
        $notifyAmount = isset($response['amount']) ? (int)$response['amount'] : null;
        if ($notifyAmount === null || $notifyAmount !== $expectedAmount) {
            $errors[] = __(
                'MoMo amount mismatch (expected %1, got %2).',
                [$expectedAmount, $notifyAmount ?? 'unknown']
            );
        }

        return $this->createResult(empty($errors), $errors);
    }

    /**
     * Verify the MoMo signature over the signed result fields.
     *
     * @param string $signature
     * @param array $response
     * @return bool
     */
    private function verifySignature(string $signature, array $response): bool
    {
        if ($signature === '') {
            return false;
        }

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
