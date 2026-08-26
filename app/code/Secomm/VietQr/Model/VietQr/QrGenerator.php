<?php

declare(strict_types=1);

namespace Secomm\VietQr\Model\VietQr;

use Magento\Sales\Api\Data\OrderInterface;
use Secomm\VietQr\Api\QrGeneratorInterface;

/**
 * Generates VietQR codes by delegating to the API client.
 *
 * Builds the request payload from the order and forwards it to the VietQR API.
 */
class QrGenerator implements QrGeneratorInterface
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly RequestBuilder $requestBuilder
    ) {
    }

    /**
     * @param OrderInterface $order
     * @return QrResult
     */
    public function generate(OrderInterface $order): QrResult
    {
        $request = $this->requestBuilder->build($order);
        return $this->apiClient->generate($request);
    }
}
