<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Logger;

class Logger extends \Monolog\Logger
{

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $message The log message
     * @param string $filePath The log name
     * @param mixed[]           $context The log context
     */
    public function error($message, array $context = [], string $filePath = ''): void
    {
        $callStack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $filePath = $this->getPath($callStack, $filePath);
        $this->addRecord(static::ERROR, "[$filePath] " . $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $message The log message
     * @param mixed[]           $context The log context
     */
    public function info($message, array $context = [], string $filePath = ''): void
    {
        $callStack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $filePath = $callStack[0]['file'] . ':' . $callStack[0]['line'];
        $position = strpos($filePath, 'app/code/');
        if ($position !== false) {
            $filePath = substr($filePath, $position + strlen('app/code/'));
        }
        $this->addRecord(static::INFO, "[$filePath] " . $message, $context);
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $message The log message
     * @param mixed[]           $context The log context
     */
    public function debug($message, array $context = [], string $filePath = ''): void
    {
        $callStack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $filePath = $this->getPath($callStack, $filePath);
        $this->addRecord(static::DEBUG, "[$filePath] " . $message, $context);
    }

    private function getPath(array $callStack, $path = ''): string
    {
        if ($path) {
            return $path;
        }
        $filePath = $callStack[0]['file'] . ':' . $callStack[0]['line'];
        $position = strpos($filePath, 'app/code/');
        if ($position !== false) {
            $filePath = substr($filePath, $position + strlen('app/code/'));
        }
        return $filePath;
    }
}
