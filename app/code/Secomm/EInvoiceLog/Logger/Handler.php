<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger as MonologLogger;

class Handler extends Base
{
    /**
     * @var int
     */
    protected $loggerType = MonologLogger::DEBUG;

    /**
     * @var string
     */
    protected $fileName = '/var/log/secomm_einvoice.log';
}
