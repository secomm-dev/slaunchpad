<?php
/**
 * Validates the MoMo refund response (resultCode 0 = success).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class RefundValidator extends AbstractValidator
{
    public const RESULT_CODE = 'resultCode';
    public const SUCCESS = 0;

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);
        $isValid = isset($response[self::RESULT_CODE])
            && (int)$response[self::RESULT_CODE] === self::SUCCESS;

        $errorMessages = [];
        if (!$isValid) {
            $message = $response['message'] ?? 'MoMo declined the refund request.';
            $errorMessages = [__('MoMo refund: %1', [$message])];
        }

        return $this->createResult($isValid, $errorMessages);
    }
}
