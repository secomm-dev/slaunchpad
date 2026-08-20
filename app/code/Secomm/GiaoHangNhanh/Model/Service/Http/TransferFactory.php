<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Http;

use Secomm\GiaoHangNhanh\Model\Service\Helper\Authorization;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\ConfigInterface;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\TransferBuilder;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\TransferFactoryInterface;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\TransferInterface;

/**
 * Class TransferFactory
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Http
 */
class TransferFactory implements TransferFactoryInterface
{
    /**
     * @var TransferBuilder
     */
    private $transferBuilder;

    /**
     * @var Authorization
     */
    private $authorization;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var null|string
     */
    protected $path;

    /**
     * @param Authorization $authorization
     * @param TransferBuilder $transferBuilder
     * @param ConfigInterface $config
     * @param null $path
     */
    public function __construct(
        Authorization $authorization,
        TransferBuilder $transferBuilder,
        ConfigInterface $config,
        $path = null
    ) {
        $this->authorization = $authorization;
        $this->transferBuilder = $transferBuilder;
        $this->config = $config;
        $this->path = $path;
    }

    /**
     * Builds service transfer object
     *
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request)
    {
        $header = $this->getAuthorization()
            ->setParameter($request)
            ->getHeaders($request);
        $body = $this->getAuthorization()->getParameter();
        return $this->transferBuilder
            ->setMethod('POST')
            ->setHeaders($header)
            ->setBody($body)
            ->setUri($this->getUri())
            ->build();
    }

    /**
     * @return Authorization
     */
    protected function getAuthorization()
    {
        return $this->authorization;
    }

    /**
     * @return mixed|string
     */
    protected function getUri()
    {
        $baseUrl = $this->isSandboxMode() ? $this->config->getValue('giaohangnhanh_sandbox_url')
            : $this->config->getValue('giaohangnhanh_url');

        return $baseUrl . '/' . $this->config->getValue($this->path);
    }

    /**
     * Whether sandbox mode is enabled in configuration
     *
     * @return bool
     */
    protected function isSandboxMode()
    {
        return $this->config && (bool)$this->config->getValue('sandbox_flag');
    }
}
