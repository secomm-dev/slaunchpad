<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   Giao hang nhanh
 */
namespace Boolfly\GiaoHangNhanh\Model\Service\Validator;

use Boolfly\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Boolfly\GiaoHangNhanh\Model\Service\Request\AbstractDataBuilder;
use Boolfly\IntegrationBase\Model\Service\Validator\ResultInterface;

/**
 * Class SynchronizeOrderValidator
 *
 * @package Boolfly\GiaoHangNhanh\Model\Service\Validator
 */
class SynchronizeOrderValidator extends AbstractResponseValidator
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
            $errorMessages = [__('Something went wrong when synchronize order.')];
        }

        return $this->createResult($validationResult, $errorMessages);
    }
}
