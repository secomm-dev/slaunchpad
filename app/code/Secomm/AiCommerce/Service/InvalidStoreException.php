<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service;

/**
 * Store context rejection (400 invalid_store) — an unknown, inactive or
 * malformed `store` parameter. Subclass of InvalidParameterException so
 * existing generic handling keeps working, but the envelope maps it to the
 * distinct invalid_store code required by the contract.
 */
class InvalidStoreException extends InvalidParameterException
{
}
