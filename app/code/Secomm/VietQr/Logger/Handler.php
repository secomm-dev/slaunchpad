<?php

declare(strict_types=1);

namespace Secomm\VietQr\Logger;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Monolog\Logger;

class Handler extends BaseHandler
{
    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * File name
     *
     * @var string
     */
    protected $fileName = '/var/log/secomm_vietqr.log';
}
