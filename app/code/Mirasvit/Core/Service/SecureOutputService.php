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


declare(strict_types=1);


namespace Mirasvit\Core\Service;


class SecureOutputService
{
    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function cleanupOne($value)
    {
        if (is_numeric($value) || is_bool($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        $value = preg_replace('/<script.*?<\/script>/is', '', $value);
        $value = str_ireplace('javascript:', '', $value);

        // remove js event attributes
        while (preg_match('/<[^>]*[\s\/](on\w*\s*=\s*[\'"]?[^\s>]*)[^>]*>/is', $value)) {
            $value = preg_replace_callback(
                '/<[^>]*[\s\/](on\w*\s*=\s*[\'"]?[^\s>]*)[^>]*>/is',
                [SecureOutputService::class, 'replaceEventAttrCallback'],
                $value
            );
        }

        return $value;
    }

    public static function cleanupArray($array): array
    {
        foreach ($array as $key => $value) {
            if (is_object($value)) {
                continue;
            }

            $array[$key] = is_array($value)
                ? self::cleanupArray($value)
                : self::cleanupOne($value);
        }

        return $array;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function replaceEventAttrCallback(array $match): string
    {
        return str_replace($match[1], '', $match[0]);
    }
}
