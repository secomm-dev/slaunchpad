<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Warehouse;

use Magento\Framework\Exception\LocalizedException;

/**
 * Thrown when warehouse map UNIQUE constraints would be violated.
 */
class WarehouseMapConflictException extends LocalizedException
{
}
