<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api;

use Magento\Framework\Exception\NoSuchEntityException;

/**
 * FEAT-2PZQKJ / TASK-NW66H9 — thrown when a profile code is used programmatically but no module
 * declares it in etc/address_profiles.xml. The storefront resolver path NEVER throws this:
 * it logs a warning and falls back to native Magento behavior.
 */
class NoSuchProfileException extends NoSuchEntityException
{
}
