<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Dedicated codrisk.log — keeps the per-checkout trace out of system.log.
 */
class Handler extends Base
{
    protected $loggerType = Logger::INFO;

    protected $fileName = '/var/log/codrisk.log';
}
