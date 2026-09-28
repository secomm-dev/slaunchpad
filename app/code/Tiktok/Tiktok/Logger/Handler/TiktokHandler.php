<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Logger\Handler;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;

class TiktokHandler extends BaseHandler
{
    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = MonologLogger::INFO;

    /**
     * Log File name
     *
     * @var string
     */
    protected $fileName = '/var/log/tiktok.log';

    /**
     * @inheritDoc
     */
    public function isHandling(array|LogRecord $record): bool
    {
        if (is_object($this->level)) {
            return $record['level'] === $this->level->value;
        }
        return $record['level'] === $this->level;
    }
}
