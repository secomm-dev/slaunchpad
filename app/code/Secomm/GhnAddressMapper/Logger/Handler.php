<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger as MonologLogger;

class Handler extends Base
{
    protected $loggerType = MonologLogger::DEBUG;
    protected $fileName = '/var/log/secomm_ghn_address_mapper.log';
}
