<?php
/**
 * Builds the HTTP transfer (POST JSON) to a MoMo endpoint.
 *
 * The target path is injected (constant) so one class serves create/refund/query.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Http;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Secomm\MoMo\Model\Config;

class TransferFactory implements TransferFactoryInterface
{
    /**
     * Constructor
     *
     * @param TransferBuilder $transferBuilder
     * @param Config $config
     * @param Json $serializer
     * @param string $urlPath
     */
    public function __construct(
        private readonly TransferBuilder $transferBuilder,
        private readonly Config $config,
        private readonly Json $serializer,
        private readonly string $urlPath = Config::PATH_CREATE
    ) {
    }

    /**
     * Build the transfer for a MoMo JSON API call.
     *
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request): TransferInterface
    {
        return $this->transferBuilder
            ->setMethod('POST')
            ->setHeaders(['Content-Type' => 'application/json'])
            ->setBody($this->serializer->serialize($request))
            ->setUri($this->config->getEndpointUrl($this->urlPath))
            ->build();
    }
}
