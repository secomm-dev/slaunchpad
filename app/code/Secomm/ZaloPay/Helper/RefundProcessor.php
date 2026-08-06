<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Helper;

class RefundProcessor
{
    const REFUND_MESSAGES = [
        1 => "Refund successful.",
        2 => "Refund failed.",
        3 => "Refund in progress.",
        -3 => "Invalid authentication information.",
        -10 => "Invalid app information.",
        -13 => "Refund time has expired.",
        -24 => "Invalid refund ID format.",
        -25 => "Refund ID has incorrect time.",
        -26 => "Refund ID has incorrect app ID.",
        -1 => "System error.",
        -2 => "Merchant error - Invalid refund type.",
        -4 => "Merchant error - PMC does not support refund.",
        -5 => "Merchant error - Transaction not complete.",
        -6 => "Merchant error - Transaction failed.",
        -7 => "Merchant error - Invalid time.",
        -8 => "Merchant error - Transaction not found.",
        -9 => "Merchant error - Transaction type does not support refund.",
        -11 => "Merchant error - Transaction already successful.",
        -12 => "Merchant error - Invalid request format.",
        -14 => "Merchant error - Invalid refund amount.",
        -15 => "System error - Failed to insert refund partial history.",
        -16 => "System error - Failed to insert refund log AR.",
        -17 => "System error - Failed to update refund log AR.",
        -18 => "System error - Transaction status does not support refund.",
        -19 => "System error - Failed to insert refund transaction log.",
        -20 => "System error - Failed to update total refund amount.",
        -21 => "System error - Refund not found.",
        -22 => "System error - Refund status does not support refund.",
        -23 => "Merchant error - Duplicate refund.",
        -24 => "Merchant error - Invalid merchant refund ID format.",
        -25 => "Merchant error - Invalid merchant refund ID date.",
        -26 => "Merchant error - Invalid merchant refund ID app ID.",
        -27 => "Merchant error - Invalid client ID.",
        -28 => "Merchant error - Invalid transaction.",
        -29 => "System error - Bank system not found.",
        -30 => "System error - Refund cache and database inconsistent.",
        -31 => "System error - Refund over time.",
        -401 => "Merchant error - Invalid request data.",
        -402 => "Merchant error - Illegal app request.",
        -403 => "Merchant error - Illegal signature request.",
        -405 => "Merchant error - Illegal client request.",
        -429 => "System error - Limit request reach.",
        -500 => "System error.",
    ];

    public static function processRefundStatus($code): string
    {
        return self::REFUND_MESSAGES[$code] ?? "Unknown refund status.";
    }
}
