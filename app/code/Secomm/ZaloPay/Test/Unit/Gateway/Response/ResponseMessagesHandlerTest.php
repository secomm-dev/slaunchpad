<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Gateway\Response\ResponseMessagesHandler;

/**
 * RESPONSE 31-34 (TASK-CG6BM7): consumer semantics of
 * ResponseMessagesHandler (mounted as ZaloPayRefundResponseHandler in the
 * refund response handler chain):
 *
 *  - return_code 1            -> approve_messages, no fraud flag;
 *  - return_code 2 / unknown  -> error_messages + setIsFraudDetected(true);
 *  - return_code 3 PROCESSING -> message recorded, NO fraud flag (PROCESSING
 *    is a normal async provider state, not fraud/error);
 *  - missing / non-numeric return_code -> no fatal, no state mutation.
 */
class ResponseMessagesHandlerTest extends TestCase
{
    /**
     * @param int|null $returnCode
     * @param string|null $returnMessage
     * @return array
     */
    private function handlingSubject(?int $returnCode = null, ?string $returnMessage = null): array
    {
        return $this->subjectForResponse(
            array_filter(
                [
                    'return_code' => $returnCode,
                    'return_message' => $returnMessage,
                ],
                static fn ($value) => $value !== null
            )
        );
    }

    /**
     * @param array $response
     * @return array
     */
    private function subjectForResponse(array $response): array
    {
        $payment = $this->createMock(Payment::class);
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        return ['payment' => $paymentDO, 'payment_mock' => $payment, 'response' => $response];
    }

    /**
     * RESPONSE 31: return_code 1 -> approve_messages, no fraud flag.
     */
    public function testSuccessMarksApprovalWithoutFraudFlag(): void
    {
        $subject = $this->handlingSubject(1, 'Refund successful.');
        $payment = $subject['payment_mock'];

        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('approve_messages', 'Refund successful.');
        $payment->expects($this->never())->method('setIsTransactionPending');
        $payment->expects($this->never())->method('setIsFraudDetected');

        (new ResponseMessagesHandler())->handle(
            ['payment' => $subject['payment']],
            $subject['response']
        );
    }

    /**
     * RESPONSE 32: return_code 2 -> error_messages + fraud flag.
     */
    public function testFailSetsErrorAndFraudFlag(): void
    {
        $subject = $this->handlingSubject(2, 'Refund failed.');
        $payment = $subject['payment_mock'];

        $payment->expects($this->once())->method('setIsTransactionPending')->with(false);
        $payment->expects($this->once())->method('setIsFraudDetected')->with(true);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('error_messages', 'Refund failed.');

        (new ResponseMessagesHandler())->handle(
            ['payment' => $subject['payment']],
            $subject['response']
        );
    }

    /**
     * RESPONSE 33: return_code 3 PROCESSING -> message recorded but NO fraud
     * flag — PROCESSING is a normal provider state.
     */
    public function testProcessingRecordsMessageWithoutFraudFlag(): void
    {
        $subject = $this->handlingSubject(3, 'Refund in progress.');
        $payment = $subject['payment_mock'];

        $payment->expects($this->once())->method('setIsTransactionPending')->with(false);
        $payment->expects($this->never())->method('setIsFraudDetected');
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('error_messages', 'Refund in progress.');

        (new ResponseMessagesHandler())->handle(
            ['payment' => $subject['payment']],
            $subject['response']
        );
    }

    /**
     * RESPONSE 34a: an unknown code is an error + fraud (legacy semantics
     * preserved for non-1/non-3 codes).
     */
    public function testUnknownCodeSetsErrorAndFraudFlag(): void
    {
        $subject = $this->handlingSubject(99, 'Weird.');
        $payment = $subject['payment_mock'];

        $payment->expects($this->once())->method('setIsFraudDetected')->with(true);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('error_messages', 'Weird.');

        (new ResponseMessagesHandler())->handle(
            ['payment' => $subject['payment']],
            $subject['response']
        );
    }

    /**
     * RESPONSE 34b: a missing or non-numeric return_code is a protocol
     * anomaly — no fatal, no payment-state mutation at all.
     */
    public function testMissingOrNonNumericReturnCodeMutatesNothing(): void
    {
        $handler = new ResponseMessagesHandler();

        foreach ([[], ['return_code' => 'not-a-number']] as $response) {
            $subject = $this->subjectForResponse($response);
            $payment = $subject['payment_mock'];

            $payment->expects($this->never())->method('setAdditionalInformation');
            $payment->expects($this->never())->method('setIsFraudDetected');
            $payment->expects($this->never())->method('setIsTransactionPending');

            $handler->handle(['payment' => $subject['payment']], $subject['response']);
        }
    }
}
