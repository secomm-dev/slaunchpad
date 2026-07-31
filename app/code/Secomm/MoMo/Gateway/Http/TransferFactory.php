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

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Secomm\MoMo\Model\Config;

class TransferFactory implements TransferFactoryInterface
{
    /**
     * @var TransferBuilder
     */
    private TransferBuilder $transferBuilder;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * MoMo API path (one of Config::PATH_*).
     *
     * @var string
     */
    private string $urlPath;

    /**
     * Constructor
     *
     * @param TransferBuilder $transferBuilder
     * @param Config $config
     * @param string $urlPath
     */
    public function __construct(
        TransferBuilder $transferBuilder,
        Config $config,
        string $urlPath = Config::PATH_CREATE
    ) {
        $this->transferBuilder = $transferBuilder;
        $this->config = $config;
        $this->urlPath = $urlPath;
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
            ->setBody(json_encode($request, JSON_UNESCAPED_UNICODE))
            ->setUri($this->config->getEndpointUrl($this->urlPath))
            ->build();
    }
}
