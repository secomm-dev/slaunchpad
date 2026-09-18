<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Response;

use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Secomm\ZaloPay\Helper\RefundProcessor;

class ResponseMessagesHandler implements HandlerInterface
{
    /**
     * @param array $handlingSubject
     * @param array $response
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        ContextHelper::assertOrderPayment($payment);

        $responseCode = $this->readReturnCode($response);
        if ($responseCode === null) {
            // Protocol anomaly: without a code, no fatal and no payment-state mutation.
            return;
        }
        $messages = $response[AbstractResponseValidator::RESPONSE_MESSAGE]
            ?? RefundProcessor::processRefundStatus($responseCode);
        $state = $this->getState($responseCode);

        if ($state) {
            $payment->setAdditionalInformation(
                'approve_messages',
                $messages
            );
        } else {
            $payment->setIsTransactionPending(false);
            // TASK-CG6BM7: PROCESSING (return_code 3) is a normal provider state, not fraud/error.
            if ($responseCode !== AbstractResponseValidator::REFUND_PROCESSING) {
                $payment->setIsFraudDetected(true);
            }
            $payment->setAdditionalInformation('error_messages', $messages);
        }
    }

    /**
     * Safe read of the provider return_code (TASK-CG6BM7): missing or
     * non-numeric codes are protocol anomalies — no fatal, no coercion.
     *
     * @param array $response
     * @return int|null
     */
    private function readReturnCode(array $response): ?int
    {
        $code = $response[AbstractResponseValidator::RETURN_CODE] ?? null;

        return is_numeric($code) ? (int)$code : null;
    }

    /**
     * @param integer $responseCode
     * @return boolean
     */
    protected function getState(int $responseCode): bool
    {
        return $responseCode === AbstractResponseValidator::RETURN_CODE_ACCEPT;
    }
}
