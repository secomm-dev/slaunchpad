<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Api;

/**
 * Encodes and decodes versioned widget parameter payloads.
 */
interface ParameterCodecInterface
{
    public const FORMAT_VERSION = 1;

    /**
     * Encode structured component data into a Magento directive-safe value.
     *
     * @param array $data Structured scalar data.
     */
    public function encode(array $data): string;

    /**
     * Decode a payload and enforce structural limits.
     *
     * @param string $payload Encoded payload.
     * @return array<string, mixed>|null Null when the payload is invalid.
     */
    public function decode(string $payload): ?array;
}
