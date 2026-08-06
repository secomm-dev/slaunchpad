<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Http\Converter;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\ConverterInterface;
use Psr\Log\LoggerInterface;

class JsonToArray implements ConverterInterface
{
    /**
     * JsonToArray constructor.
     *
     * @param Json $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Json            $serializer,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * Converts gateway response to array structure
     *
     * @param mixed $response
     * @return array
     * @throws ConverterException
     */
    public function convert($response): array
    {
        try {
            return $this->serializer->unserialize($response);
        } catch (\Exception $e) {
            $this->logger->critical('Can\'t read response from ZaloPay');
            throw new ConverterException(__('Can\'t read response from ZaloPay'));
        }
    }
}
