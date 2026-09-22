<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * BUG-JBX3H9 — the local Magento→GHN location mapping could not produce the required
 * GHN district/ward identity for a location (destination OR shipping origin).
 *
 * Fail-closed contract: callers must NOT substitute hardcoded GHN ids — the rate
 * path treats this as "carrier unavailable for the address" and the order-sync
 * path fails the sync explicitly. Pattern follows the existing module exception
 * style (CommandException extends LocalizedException).
 */
class GhnLocationMappingException extends LocalizedException
{
}
