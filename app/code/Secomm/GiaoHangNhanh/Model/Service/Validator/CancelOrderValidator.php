<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Validator;

use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Secomm\GiaoHangNhanh\Model\Integration\Validator\ResultInterface;

/**
 * Class CancelOrderValidator
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Validator
 */
class CancelOrderValidator extends AbstractResponseValidator
{
    /**
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $errorMessages = [];
        $response = SubjectReader::readResponse($validationSubject);
        $validationResult = $this->validateResponseMsg($response);

        if (!$validationResult) {
            $errorMessages = [__('Something went wrong when cancel order.')];
        }

        return $this->createResult($validationResult, $errorMessages);
    }
}
