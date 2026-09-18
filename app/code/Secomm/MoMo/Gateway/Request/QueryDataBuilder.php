<?php
/**
 * Builds the MoMo v2/query (transaction status) request body.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Model\Config;

/**
 * v2/query request: partnerCode, orderId, requestId + signature over
 * accessKey&orderId&partnerCode&requestId (MoMo's documented query field
 * order). orderId = the attempt's order_ref.
 */
class QueryDataBuilder implements BuilderInterface
{
    /**
     * QueryDataBuilder constructor.
     *
     * @param Config $config
     * @param Signature $signature
     */
    public function __construct(
        private readonly Config $config,
        private readonly Signature $signature
    ) {
    }

    /**
     * Build the v2/query request body for an attempt's order_ref.
     *
     * @param array $buildSubject expects: order_ref (string).
     * @return array<string, scalar>
     * @throws \InvalidArgumentException When order_ref is missing.
     */
    public function build(array $buildSubject): array
    {
        $orderRef = (string)($buildSubject['order_ref'] ?? '');
        if ($orderRef === '') {
            throw new \InvalidArgumentException('order_ref should be provided');
        }
        $requestId = (string)($buildSubject['request_id'] ?? '');

        $rawParams = [
            'accessKey' => $this->config->getAccessKey(),
            'orderId' => $orderRef,
            'partnerCode' => $this->config->getPartnerCode(),
            'requestId' => $requestId,
        ];

        return [
            'partnerCode' => $this->config->getPartnerCode(),
            'orderId' => $orderRef,
            'requestId' => $requestId,
            'lang' => 'vi',
            'signature' => $this->signature->sign($rawParams, $this->config->getSecretKey()),
        ];
    }
}
