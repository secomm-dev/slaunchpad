<?php
/**
 * Converts a MoMo JSON response body to an array.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Http\Converter;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ConverterInterface;

class JsonToArray implements ConverterInterface
{
    /**
     * Constructor
     *
     * @param Json $serializer
     */
    public function __construct(
        private readonly Json $serializer
    ) {
    }

    /**
     * @inheritdoc
     */
    public function convert($response): array
    {
        try {
            $decoded = $this->serializer->unserialize((string)$response);
            return is_array($decoded) ? $decoded : [];
        } catch (\InvalidArgumentException $e) {
            return [];
        }
    }
}
