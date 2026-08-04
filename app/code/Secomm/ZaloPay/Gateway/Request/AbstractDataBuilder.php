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

namespace Secomm\ZaloPay\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class AbstractDataBuilder
 * @package Secomm\ZaloPay\Gateway\Request
 */
abstract class AbstractDataBuilder implements BuilderInterface
{
    /**@#+
     * Create Url path
     *
     * @const
     */
    const PAY_URL_PATH = 'v2/create';

    /**
     * Refund path
     */
    const REFUND_URL_PATH = 'v2/refund';

    /**
     * Refund query path
     */
    const REFUND_QUERY_URL_PATH = 'v2/query_refund';

    /**
     * Transaction Type: Refund
     */
    const REFUND = 'refund';

    /**
     * Transaction Id
     */
    const TRANSACTION_ID = 'app_trans_id';

    /**
     * App Id
     */
    const APP_ID = 'app_id';

    /**
     * App Time
     */
    const APP_TIME = 'app_time';

    /**
     * Key 1
     */
    const KEY_1 = 'key1';

    /**
     * Key 2
     */
    const KEY_2 = 'key2';

    /**
     * App transaction Id
     */
    const APP_TRANS_ID = 'app_trans_id';

    /**
     * App user
     */
    const APP_USER = 'app_user';

    /**
     * Item information
     */
    const ITEM = 'item';

    /**
     * Embed data
     */
    const EMBED_DATA = 'embed_data';

    /**
     * Description
     */
    const DESCRIPTION = 'description';

    /**
     * Bank Code
     */
    const BANK_CODE = 'bank_code';

    /**
     * Redirect to this url after paying on ZaloPay portal (override redirect url when registering app with ZaloPay)
     * Redirect to page success/fail checkout
     */
    const REDIRECT_URL = 'redirecturl';

    /**
     * ZaloPay will notify the payment status of the order when payment is completed.
     * If not provided, the application's default callback_url will be used.
     */
    const CALL_BACK = 'callback_url';

    /**
     * The application's private data
     */
    const MERCHANT_INFO = 'merchant_info';


    /**
     * Amount
     */
    const AMOUNT = 'amount';

    /**
     * Transaction Data
     */
    const TRANS_DATA = 'trans_data';

    /**@#%
     * Refund Id
     */
    const M_REFUND_ID = 'm_refund_id';

    /**
     * ZaloPay Trans ID
     */
    const ZP_TRANS_ID = 'zp_trans_id';

    /**
     * Timestamp
     */
    const TIMESTAMP = 'timestamp';

    /**
     * Mac
     */
    const MAC = 'mac';
}
