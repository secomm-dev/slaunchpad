<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Validator;

use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Validator\ResultInterface;

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
            $errorMessages = [__('GHN cancel failed: %1', $this->getGhnErrorMessage($response))];
        }

        return $this->createResult($validationResult, $errorMessages);
    }
}
