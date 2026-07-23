<?php

namespace Secomm\Base\Model;

class Logger extends \Zend_Log
{
    protected bool $inShell;

    public function setInShell($inShell = false)
    {
        $this->inShell = $inShell;
    }

    /**
     * @param string|array|object $message
     * @param int|mixed $priority
     * @param array $extra
     * @return void
     */
    public function log($message, $priority = \Zend_Log::INFO, $extra = []): void
    {
        // Print out command line if isShell is true
        if ($this->inShell) {
            if (is_array($message) || is_object($message)) {
                print_r(json_encode($message) . "\n");
            } else {
                print_r($message . "\n");
            }

        }
        try {
            parent::log($message, $priority, $extra);
        } catch (\Zend_Log_Exception $e) {
            //DO NOTHING
        }
    }

    /**
     * @param string|array|object $message
     * @param array|\Traversable $extra
     * @return void
     * @throws \Zend_Log_Exception
     */
    public function emerg($message, $extra = []): void
    {
        $this->log($message, self::EMERG, $extra);
    }

    /**
     * @param string|array|object $message
     * @param array|\Traversable $extra
     * @return void
     */
    public function alert($message, $extra = []): void
    {
        $this->log($message, self::ALERT, $extra);
    }

    /**
     * @param string|array|object $message
     * @param array|\Traversable $extra
     * @return void
     */
    public function crit($message, $extra = []): void
    {
        $this->log($message, self::CRIT, $extra);
    }

    /**
     * @param string|array|object $message
     * @param array|\Traversable $extra
     * @return void
     */
    public function err($message, $extra = []): void
    {
        $this->log($message, self::ERR, $extra);
    }

    /**
     * @param string|array|object $message
     * @param array|\Traversable $extra
     * @return void
     */
    public function warn($message, $extra = []): void
    {
        $this->log($message, self::WARN, $extra);
    }

    /**
     * @param string|array|object $message
     * @param array|\Traversable $extra
     * @return void
     */
    public function notice($message, $extra = []): void
    {
        $this->log($message, self::NOTICE, $extra);
    }

    /**
     * @param string|array|object $message
     * @param array|\Traversable $extra
     * @return void
     */
    public function info($message, $extra = []): void
    {
        $this->log($message, self::INFO, $extra);
    }

    /**
     * @param string|array|object $message
     * @param array|\Traversable $extra
     * @return void
     */
    public function debug($message, $extra = []): void
    {
        $this->log($message, self::DEBUG, $extra);
    }
}
