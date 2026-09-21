<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Logger;

use Monolog\Logger;

/**
 * DEBUG-level handler for the masked provider request/response payload
 * (TASK-MCHN2T). Shares the ZaloPay log file with the module INFO handler:
 * when Admin Debug Mode (`payment/zalopay/debug`), the core payment method
 * logger gates before Monolog, so this handler receives records only from
 * the gated HTTP-client debug path (plus OrderFinalizer-style internal
 * debug records only when the flag is on).
 */
class DebugHandler extends Handler
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::DEBUG;
}
