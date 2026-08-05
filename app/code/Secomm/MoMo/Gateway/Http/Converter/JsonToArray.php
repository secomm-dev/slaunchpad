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

use Magento\Payment\Gateway\Http\ConverterInterface;

class JsonToArray implements ConverterInterface
{
    /**
     * @inheritdoc
     */
    public function convert($response): array
    {
        $decoded = json_decode((string)$response, true);

        return is_array($decoded) ? $decoded : [];
    }
}
