<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\GiaoHangNhanh\Model\Integration\Logger;

use Secomm\GiaoHangNhanh\Model\Integration\ConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Logger
 * @package Secomm\GiaoHangNhanh\Model\Integration\Logger
 */
class Logger
{

    const DEBUG_KEYS_MASK = '****';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param LoggerInterface $logger
     * @param ConfigInterface|null $config
     */
    public function __construct(
        LoggerInterface $logger,
        ?ConfigInterface $config = null
    ) {
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Logs integration related information used for debug
     *
     * @param array $data
     * @param array|null $maskKeys
     * @param bool|null $forceDebug
     * @return void
     */
    public function debug(array $data, ?array $maskKeys = null, ?bool $forceDebug = null)
    {
        $maskKeys = $maskKeys !== null ? $maskKeys : $this->getDebugReplaceFields();
        $debugOn = $forceDebug !== null ? $forceDebug : $this->isDebugOn();
        if ($debugOn === true) {
            $data = $this->filterDebugData(
                $data,
                $maskKeys
            );
            $this->logger->debug(var_export($data, true));
        }
    }

    /**
     * Returns configured keys to be replaced with mask
     *
     * @return array
     */
    private function getDebugReplaceFields()
    {
        if ($this->config && $this->config->getValue('debugReplaceKeys')) {
            return explode(',', $this->config->getValue('debugReplaceKeys'));
        }
        return [];
    }

    /**
     * Whether debug is enabled in configuration
     *
     * @return bool
     */
    private function isDebugOn()
    {
        return $this->config && (bool)$this->config->getValue('debug');
    }

    /**
     * Recursive filter data by private conventions
     *
     * @param array $debugData
     * @param array $debugReplacePrivateDataKeys
     * @return array
     */
    protected function filterDebugData(array $debugData, array $debugReplacePrivateDataKeys)
    {
        $debugReplacePrivateDataKeys = array_map('strtolower', $debugReplacePrivateDataKeys);

        foreach (array_keys($debugData) as $key) {
            if (in_array(strtolower($key), $debugReplacePrivateDataKeys)) {
                $debugData[$key] = self::DEBUG_KEYS_MASK;
            } elseif (is_array($debugData[$key])) {
                $debugData[$key] = $this->filterDebugData($debugData[$key], $debugReplacePrivateDataKeys);
            }
        }
        return $debugData;
    }
}
