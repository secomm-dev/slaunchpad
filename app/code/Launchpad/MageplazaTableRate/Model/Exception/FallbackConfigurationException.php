<?php
/*
 * TASK-NQT782 (LT-BRIDGE-1) — broken bridge configuration.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * The fallback MAPPING is broken (configured Mageplaza method does not exist, or is
 * inactive/out of scope for the store). This must fail loudly: a broken emergency-pricing
 * profile is misconfiguration and must never silently degrade into "no fallback rate available".
 * Pattern follows the module exception style of GhnLocationMappingException.
 */
class FallbackConfigurationException extends LocalizedException
{
}
