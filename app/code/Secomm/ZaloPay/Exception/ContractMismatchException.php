<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * The CURRENT quote no longer represents the payment contract the provider
 * was paid for (BLOCKER 1). Thrown by OrderFinalizer BEFORE placeOrder:
 *
 *  - the attempt STAYS PAID (provider money is real — never FAILED);
 *  - order_id stays NULL, no order is auto-created;
 *  - the reason is recorded on the attempt last_error for
 *    reconciliation / manual placement / refund.
 */
class ContractMismatchException extends LocalizedException
{
}
