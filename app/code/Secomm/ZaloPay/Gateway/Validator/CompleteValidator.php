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

class CompleteValidator extends AbstractResponseValidator
{
    /**
     * @param array $validationSubject
     * @return ResultInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);
        $amount = round(SubjectReader::readAmount($validationSubject), 2);
        $payment = SubjectReader::readPayment($validationSubject);
        $amount = $this->helperRate->getVndAmount($payment->getPayment()->getOrder(), $amount);
        $validationResult = $this->validateTotalAmount($response, $amount)
            && $this->validateTransactionId($response)
            && $this->validateMac($response);

        $errorMessages = [];
        if (!$validationResult) {
            $errorMessages = [__('Transaction has been declined. Please try again later.')];
        }

        return $this->createResult($validationResult, $errorMessages);
    }

    /**
     * Validate Mac By Key 2
     *
     * @param $response
     * @return boolean
     */
    protected function validateMac($response): bool
    {
        $macKey2 = $this->authorization->getMacKey2($response['data']);
        return $response[AbstractDataBuilder::MAC] === $macKey2;
    }

    /**
     * Validate total amount.
     *
     * @param array $response
     * @param array|string $amount
     * @return boolean
     */
    protected function validateTotalAmount(array $response, array|string $amount): bool
    {
        return isset($response[AbstractDataBuilder::TRANS_DATA][self::TOTAL_AMOUNT])
            && (string)($response[AbstractDataBuilder::TRANS_DATA][self::TOTAL_AMOUNT]) === (string)$amount;
    }
}
