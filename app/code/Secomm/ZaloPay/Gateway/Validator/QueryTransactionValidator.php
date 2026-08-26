<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\ResultInterface;

/**
 * Structural validation of the v2/query (transaction status) response.
 *
 * Only checks that the response is a well-formed status answer
 * (return_code present; zp_trans_id present when the transaction is paid).
 * Business decisions — return_code mapping and the amount comparison
 * against the persisted attempt snapshot — belong to the caller.
 */
class QueryTransactionValidator extends AbstractResponseValidator
{
    /**
     * return_code values the v2/query API is documented to answer with.
     */
    private const KNOWN_RETURN_CODES = [1, 2, 3];

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);
        $errorMessages = [];

        $isValid = isset($response[self::RETURN_CODE])
            && in_array((int)$response[self::RETURN_CODE], self::KNOWN_RETURN_CODES, true);

        if ($isValid && (int)$response[self::RETURN_CODE] === self::RETURN_CODE_ACCEPT) {
            // A "paid" answer must identify the provider transaction.
            $isValid = !empty($response[self::ZP_TRANS_ID]);
        }

        if (!$isValid) {
            $errorMessages = [__('Unexpected ZaloPay transaction status response.')];
        }

        return $this->createResult($isValid, $errorMessages);
    }
}
