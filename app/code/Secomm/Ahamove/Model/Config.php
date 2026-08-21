<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model;

class Config
{
    /**
     * Carrier const
     */
    const IS_ACTIVE = 'active';
    const TITLE = 'title';
    const NAME = 'name';
    const AVAIABLECOUNTRY = 'VN';

    /**
     * Config
     */
    const MODE_PATH = 'ahamove/general/mode';
    const URL_SANDBOX = 'https://partner-apistg.ahamove.com';
    const URL_PRODUCTION = 'https://api.ahamove.com';

    const STAGING_API_KEY = 'ahamove/general/staging_api_key';
    const STAGING_PHONE_NUMBER = 'ahamove/general/staging_phone_number';
    const PRODUCTION_API_KEY = 'ahamove/general/production_api_key';
    const PRODUCTION_PHONE_NUMBER = 'ahamove/general/production_phone_number';

    const STAGING_TOKEN = 'ahamove/general/staging_token';
    const STAGING_TOKEN_REFRESH = 'ahamove/general/staging_token_refresh';
    const PRODUCTION_TOKEN = 'ahamove/general/production_token';
    const PRODUCTION_TOKEN_REFRESH = 'ahamove/general/production_token_refresh';

    const AHAMOVE_GENERAL_CITY_ID_SERVICE = 'ahamove/general/city_id_service';
    const AHAMOVE_GENERAL_NOTIFY_SHIPMENT = 'ahamove/general/notify_shipment';
    const AHAMOVE_GENERAL_PAYMENT_TYPE = 'ahamove/general/payment_type';
    const AHAMOVE_GENERAL_EMAIL_FROM = 'ahamove/general/email_from';


    /**
     * API const
     */
    const URL_AHAMOVE_CITY = '/v3/cities?country_id=VN';
    const SHIPPING_FEE = '/v1/orders/estimates';
    const SHIPPING_FEE_WITH_MANY_SERVICES = '/v3/orders/estimates';
    const URL_AHAMOVE_CREATE_ORDER = '/v3/orders';
    const URL_AHAMOVE_CITY_DETAILS = '/v1/order/city_detail';
    const GET_REFRESH_TOKEN = '/v3/accounts/token';
    const URL_AHAMOVE_SHARED_LINK = '/v1/order/shared_link';

    /**
     * Payment method
     */
    const PAYMENT_CASH = "CASH";
    const PAYMENT_CASH_DESCRIPTION = "Cash paid by Seller/Sender";
    const PAYMENT_CASH_BY_RECIPIENT = "CASH_BY_RECIPIENT";
    const PAYMENT_CASH_BY_RECIPIENT_DESCRIPTION = "Cash paid by Buyer/Consignee";
    const PAYMENT_BALANCE = "BALANCE";
    const PAYMENT_BALANCE_DESCRIPTION = "Payment from account (Prepaid or Postpaid)";
}
