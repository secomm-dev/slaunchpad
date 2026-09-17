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
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class TransactionRefundHandler implements HandlerInterface
{
    /**
     * @var array
     */
    private array $additionalInformationMapping = [
        'transaction_type' => AbstractResponseValidator::TRANSACTION_TYPE,
        'transaction_id' => AbstractResponseValidator::TRANSACTION_ID,
    ];

    /**
     * @param array $handlingSubject
     * @param array $response
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $orderPayment */
        $orderPayment = $paymentDO->getPayment();

        // TASK-CG6BM7: refund_id is the provider cross-check id — a missing
        // one must not fatal on an undefined key. Transaction bookkeeping is
        // skipped safely; the refund row still carries m_refund_id evidence.
        $refundId = $response[AbstractResponseValidator::REFUND_ID] ?? null;
        if ($refundId === null || $refundId === '') {
            return;
        }
        $orderPayment->setTransactionId((string)$refundId);

        $orderPayment->setIsTransactionClosed(true);
        $invoice = $orderPayment->getCreditmemo()?->getInvoice();
        $orderPayment->setShouldCloseParentTransaction($invoice !== null && !$invoice->canRefund());

        foreach ($this->additionalInformationMapping as $informationKey => $responseKey) {
            if (isset($response[$responseKey])) {
                $orderPayment->setAdditionalInformation($informationKey, ucfirst($response[$responseKey]));
            }
        }
    }
}
