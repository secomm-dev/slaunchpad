<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service;

use Magento\Framework\Exception\LocalizedException;

/**
 * Base contract for deterministic facade errors mapped by the responder.
 */
class FacadeException extends LocalizedException
{
}
