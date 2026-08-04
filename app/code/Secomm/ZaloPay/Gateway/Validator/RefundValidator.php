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

use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\ResultInterface;

class RefundValidator extends AbstractResponseValidator
{
    /**
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);
        $errorMessages = [];

        $validationResult = $this->validateRefundId($response)
            && $this->validateReturnCode($response);

        if (!$validationResult) {
            $errorMessages = [__('Transaction has been declined. Please try again later.')];
        }

        return $this->createResult($validationResult, $errorMessages);
    }

    /**
     * @param array $response
     * @return boolean
     */
    protected function validateRefundId(array $response): bool
    {
        return isset($response[AbstractResponseValidator::REFUND_ID])
            && $response[AbstractResponseValidator::REFUND_ID];
    }

    /**
     * @param array $response
     * @return boolean
     */
    protected function validateReturnCode(array $response): bool
    {
        return isset($response[self::RETURN_CODE])
            && ((string)$response[self::RETURN_CODE] === (string)self::RETURN_CODE_ACCEPT
                || (string)$response[self::RETURN_CODE] === (string)self::REFUND_PROCESSING);
    }
}
