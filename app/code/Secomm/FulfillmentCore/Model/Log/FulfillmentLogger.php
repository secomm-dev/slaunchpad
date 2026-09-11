<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Log;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Secomm\FulfillmentCore\Api\PosLogConfigInterface;

/**
 * Writes fulfillment logs under var/log/fulfillment/{pos}/ when that POS enables logging.
 */
class FulfillmentLogger
{
    private const BASE_FOLDER = 'fulfillment';
    private const FILE_NAME = 'fulfillment.log';

    /**
     * @param PosLogConfigInterface[] $gates Keyed by service_code
     */
    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly File $fileDriver,
        private readonly array $gates = []
    ) {
    }

    /**
     * @param string $serviceCode POS service_code, also the folder name
     * @param array<string, scalar|null> $context Non-PII context only
     */
    public function info(string $serviceCode, string $message, array $context = []): void
    {
        $this->write($serviceCode, 'INFO', $message, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function warning(string $serviceCode, string $message, array $context = []): void
    {
        $this->write($serviceCode, 'WARNING', $message, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function error(string $serviceCode, string $message, array $context = []): void
    {
        $this->write($serviceCode, 'ERROR', $message, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function write(string $serviceCode, string $level, string $message, array $context): void
    {
        $serviceCode = $this->normalizeServiceCode($serviceCode);
        if ($serviceCode === '' || !$this->isEnabled($serviceCode)) {
            return;
        }

        try {
            $base = $this->directoryList->getPath(DirectoryList::LOG) . '/' . self::BASE_FOLDER;
            $folder = $base . '/' . $serviceCode;
            $this->fileDriver->createDirectory($base);
            $this->fileDriver->createDirectory($folder);
            $line = sprintf(
                "[%s] %s: %s %s\n",
                gmdate('c'),
                $level,
                $message,
                $this->encodeContext($context)
            );
            $this->fileDriver->filePutContents($folder . '/' . self::FILE_NAME, $line, FILE_APPEND);
        } catch (\Throwable) {
            return;
        }
    }

    private function isEnabled(string $serviceCode): bool
    {
        $gate = $this->gates[$serviceCode] ?? null;
        if (!$gate instanceof PosLogConfigInterface) {
            return false;
        }

        return $gate->isLogEnabled();
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function encodeContext(array $context): string
    {
        $safe = [];
        foreach ($context as $key => $value) {
            if ($this->isSecretKey((string) $key)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }
        $json = json_encode($safe, JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '{}';
    }

    private function isSecretKey(string $key): bool
    {
        $key = strtolower($key);
        foreach (['api_key', 'secret', 'password', 'token', 'phone', 'email', 'authorization'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeServiceCode(string $serviceCode): string
    {
        $serviceCode = strtolower(trim($serviceCode));
        $serviceCode = (string) preg_replace('/[^a-z0-9_-]/', '', $serviceCode);

        return $serviceCode;
    }
}
