<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */

namespace Secomm\ZaloPay\Gateway\Validator;

use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

/**
 * Class AbstractResponseValidator
 */
abstract class AbstractResponseValidator extends AbstractValidator
{

    /**
     * The amount that was authorised for this transaction
     */
    const TOTAL_AMOUNT = 'amount';

    /**
     * The transaction type that this transaction was processed under
     * One of: Purchase, MOTO, Recurring
     */
    const TRANSACTION_TYPE = 'transactionType';

    /**
     * Pay Url
     */
    const PAY_URL = 'order_url';

    /**
     * Transaction Id
     */
    const TRANSACTION_ID = 'app_trans_id';

    /**
     * ZaloPay Trans ID
     */
    const ZP_TRANS_ID = 'zp_trans_id';

    /**
     * Refund Id
     */
    const REFUND_ID = 'refund_id';

    /**
     * Error Code
     */
    const ERROR_CODE = 'errorCode';

    /**
     * Return Code
     */
    const RETURN_CODE = 'return_code';

    /**
     * Sub Return Code
     */
    const SUB_RETURN_CODE = 'sub_return_code';

    /**
     * Error Code Accept
     */
    const ERROR_CODE_ACCEPT = '0';

    /**
     * Return Code Accept
     */
    const RETURN_CODE_ACCEPT = 1;

    /**
     * Refund Code Fail
     */
    const REFUND_FAIL = 2;

    /**
     * Refund Code processing
     */
    const REFUND_PROCESSING = 3;

    /**
     * Message
     */
    const RESPONSE_MESSAGE = 'return_message';

    /**
     * Trans Data
     */
    const TRANS_DATA = 'trans_data';

    /**
     * @var Rate
     */
    protected Rate $helperRate;

    /**
     * @var Authorization
     */
    protected Authorization $authorization;

    /**
     * AbstractResponseValidator constructor.
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param Authorization $authorization
     * @param Rate $helperRate
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Authorization          $authorization,
        Rate                   $helperRate
    ) {
        parent::__construct($resultFactory);
        $this->helperRate = $helperRate;
        $this->authorization = $authorization;
    }

    /**
     * @param array $response
     * @return boolean
     */
    protected function validateErrorCode(array $response): bool
    {
        return isset($response[self::ERROR_CODE])
            && ((string)$response[self::ERROR_CODE] === (string)self::ERROR_CODE_ACCEPT);
    }

    /**
     * @param array $response
     * @return boolean
     */
    protected function validateReturnCode(array $response): bool
    {
        return isset($response[self::RETURN_CODE])
            && ((string)$response[self::RETURN_CODE] === (string)self::RETURN_CODE_ACCEPT);
    }

    /**
     * @param array $response
     * @return boolean
     */
    protected function validateTransactionId(array $response): bool
    {
        return isset($response[AbstractResponseValidator::TRANS_DATA][AbstractResponseValidator::ZP_TRANS_ID])
            && $response[AbstractResponseValidator::TRANS_DATA][AbstractResponseValidator::ZP_TRANS_ID];
    }
}
