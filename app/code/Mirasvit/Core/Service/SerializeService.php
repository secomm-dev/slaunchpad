<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Core\Service;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Serialize\Serializer\Serialize as PhpSerialize;
use Psr\Log\LoggerInterface;

/**
 * @deprecated Use \Magento\Framework\Serialize\Serializer\Json directly via DI.
 *
 * encode() emits JSON. decode() reads JSON, with a transparent fallback to
 * Magento's Serialize wrapper for legacy PHP-serialized values.
 */
class SerializeService
{
    private const LOG_PAYLOAD_LIMIT = 4096;

    /**
     * @var Json|null
     */
    private static $jsoner;

    /**
     * @var PhpSerialize|null
     */
    private static $phpSerializer;

    /**
     * @var LoggerInterface|null
     */
    private static $logger;

    private static function getJsoner(): Json
    {
        if (self::$jsoner === null) {
            self::$jsoner = CompatibilityService::getObjectManager()->get(Json::class);
        }

        return self::$jsoner;
    }

    private static function getPhpSerializer(): ?PhpSerialize
    {
        if (self::$phpSerializer === null) {
            try {
                self::$phpSerializer = CompatibilityService::getObjectManager()->get(PhpSerialize::class);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return self::$phpSerializer;
    }

    private static function getLogger(): ?LoggerInterface
    {
        if (self::$logger === null) {
            try {
                self::$logger = CompatibilityService::getObjectManager()->get(LoggerInterface::class);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return self::$logger;
    }

    /**
     * @deprecated No-op kept for BC. Magento < 2.3 is no longer supported.
     */
    public static function init()
    {
        self::getJsoner();
    }

    /**
     * @deprecated No-op kept for BC. Always returns false.
     */
    public static function isOldVersion(): bool
    {
        return false;
    }

    /**
     * @deprecated Use \Magento\Framework\Serialize\Serializer\Json::serialize.
     *
     * @param mixed $data
     *
     * @return string|null
     */
    public static function encode($data)
    {
        try {
            return self::getJsoner()->serialize($data);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @deprecated Use \Magento\Framework\Serialize\Serializer\Json::unserialize.
     *
     * @param string $string
     *
     * @return mixed
     */
    public static function decode($string)
    {
        if (!is_string($string)) {
            return null;
        }

        try {
            return self::getJsoner()->unserialize($string);
        } catch (\Exception $e) {
            if (strpos($string, 'a:') === 0) {
                $phpSerializer = self::getPhpSerializer();
                if ($phpSerializer !== null) {
                    try {
                        return $phpSerializer->unserialize($string);
                    } catch (\Exception $e2) {
                        // legacy decode also failed; fall through to warning
                    }
                }
            }

            $logger = self::getLogger();
            if ($logger !== null) {
                $logger->debug(
                    'Unable to unserialize data: payload is not valid JSON. '
                    . substr($string, 0, self::LOG_PAYLOAD_LIMIT),
                    ['exception' => $e]
                );
            }

            return null;
        }
    }

    /**
     * @deprecated Use Json::serialize then json_encode with the flags you need.
     *
     * @param mixed $data
     *
     * @return string|null
     */
    public static function encodeReadable($data)
    {
        $result = self::encode($data);

        if (is_string($result)) {
            $decoded = json_decode($result, true);
            if ($decoded !== null) {
                $result = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $result;
    }

    /**
     * @deprecated BC shim — Magento < 2.3 is no longer supported. Equivalent to encode().
     *
     * @param mixed $data
     *
     * @return string|null
     */
    public static function encodeWithNewMagento($data)
    {
        return self::encode($data);
    }

    /**
     * @deprecated BC shim — Magento < 2.3 is no longer supported. Equivalent to encode().
     *
     * @param mixed $data
     *
     * @return string|null
     */
    public static function encodeWithOldMagento($data)
    {
        return self::encode($data);
    }

    /**
     * @deprecated BC shim — Magento < 2.3 is no longer supported. Equivalent to decode().
     *
     * @param string $data
     *
     * @return mixed
     */
    public static function decodeWithNewMagento($data)
    {
        return self::decode($data);
    }

    /**
     * @deprecated BC shim — Magento < 2.3 is no longer supported. Equivalent to decode().
     *
     * @param string $data
     *
     * @return mixed
     */
    public static function decodeWithOldMagento($data)
    {
        return self::decode($data);
    }
}
