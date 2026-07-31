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

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

/**
 * Response handler for the Return (redirect) callback.
 *
 * Unlike the IPN payload, the Return redirect carries NO trans_data and no
 * ZaloPay transaction id (zp_trans_id) — only the app transaction id
 * (apptransid). The Return is also non-authoritative, so we must NOT register
 * a payment transaction here (the IPN handler does that authoritatively). This
 * handler is null-safe: it only persists the app transaction id as additional
 * information for traceability.
 */
class TransactionReturnHandler implements HandlerInterface
{
    /**
     * Mapping of additional_information keys -> Return response keys.
     *
     * @var array<string, string>
     */
    private array $additionalInformationMapping = [
        'transaction_id' => 'apptransid',
    ];

    /**
     * Persist Return callback data onto the payment without registering a transaction.
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $orderPayment */
        $orderPayment = $paymentDO->getPayment();

        foreach ($this->additionalInformationMapping as $informationKey => $responseKey) {
            if (isset($response[$responseKey])) {
                $orderPayment->setAdditionalInformation($informationKey, $response[$responseKey]);
            }
        }
    }
}
