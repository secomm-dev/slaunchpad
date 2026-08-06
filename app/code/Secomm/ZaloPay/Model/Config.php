<?php
declare(strict_types=1);

namespace Secomm\ZaloPay\Model;

/**
 * ZaloPay payment gateway configuration constants.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_ZaloPay
 */
class Config
{
    public const ZALO_URL = 'https://zalopay.com.vn/';
    public const LIVE_PAYMENT_ZALO_URL = 'https://openapi.zalopay.com.vn/';
    public const SANDBOX_PAYMENT_ZALO_URL = 'https://sb-openapi.zalopay.vn/';
}
