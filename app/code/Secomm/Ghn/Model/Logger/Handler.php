<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * SPEC-FEAT-FQWEQ3 §48 — dedicated GHN provider log. One context line per API call
 * (operation, shop_id, http_status, provider_code, duration_ms); sanitized payloads only when
 * debug=1. Raw token never reaches the writer (GhnLogger::sanitizeContext, unit-tested AC-A4).
 */
class Handler extends Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * @var string
     */
    protected $fileName = '/var/log/secomm_ghn.log';
}
