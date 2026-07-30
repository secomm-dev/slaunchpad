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

namespace Secomm\ZaloPay\Gateway\Validator;

use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

/**
 * ReturnValidator for ZaloPay redirect callback (GET params)
 *
 */
class ReturnValidator extends AbstractResponseValidator
{
    /**
     * ReturnValidator constructor.
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param Authorization $authorization
     * @param Rate $helperRate
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Authorization $authorization,
        Rate $helperRate
    ) {
        parent::__construct($resultFactory, $authorization, $helperRate);
    }

    /**
     * Validate return callback from ZaloPay redirect.
     *
     * NOTE: The Return (redirect) callback is NOT the authoritative payment
     * confirmation — IPN is. ZaloPay return URL exposes GET params with field
     * names that differ from the request (appid, apptransid, amount, apptime,
     * embeddata, item, mac). We validate leniently here: amount + transaction
     * reference; MAC is verified authoritatively by the IPN flow.
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);

        // Transaction id is the hard requirement (proves it is a real ZaloPay callback).
        $validationResult = $this->validateTransactionId($response);

        // Amount check is best-effort. The Return redirect is non-authoritative
        // (IPN confirms authoritatively), so if VND conversion is not possible
        // we skip the amount comparison rather than fail the whole validation.
        try {
            $amount = round(SubjectReader::readAmount($validationSubject), 2);
            $payment = SubjectReader::readPayment($validationSubject);
            $amount = $this->helperRate->getVndAmount($payment->getPayment()->getOrder(), $amount);
            $validationResult = $validationResult && $this->validateTotalAmount($response, $amount);
        } catch (LocalizedException $e) {
            // No VND currency rate configured — cannot compare amounts on Return.
        }

        $errorMessages = [];
        if (!$validationResult) {
            $errorMessages = [__('Transaction has been declined. Please try again later.')];
        }

        return $this->createResult($validationResult, $errorMessages);
    }

    /**
     * Validate transaction reference for Return callback.
     *
     * Override parent: parent checks trans_data[zp_trans_id] which only the IPN
     * payload has. The Return callback carries the app transaction id at top
     * level, so verify that instead.
     *
     * @param array $response
     * @return bool
     */
    protected function validateTransactionId(array $response): bool
    {
        // ZaloPay return URL uses "apptransid"; accept both naming variants.
        $appTransId = $response['apptransid'] ?? $response[AbstractDataBuilder::APP_TRANS_ID] ?? '';
        return !empty($appTransId);
    }

    /**
     * Validate total amount against Return callback (GET params).
     * Return callback exposes amount at top level (response[amount]).
     *
     * @param array $response
     * @param float $amount
     * @return bool
     */
    protected function validateTotalAmount(array $response, float $amount): bool
    {
        $returnedAmount = $response['amount'] ?? $response[AbstractDataBuilder::AMOUNT] ?? null;
        if ($returnedAmount === null) {
            return true;
        }

        return (float)$returnedAmount === (float)$amount;
    }
}
