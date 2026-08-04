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

namespace Secomm\ZaloPay\Gateway\Http;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Secomm\ZaloPay\Gateway\Helper\Authorization;

/**
 * Class AbstractTransferFactory
 */
abstract class AbstractTransferFactory implements TransferFactoryInterface
{
    /**
     * AbstractTransferFactory constructor.
     *
     * @param ConfigInterface $config
     * @param TransferBuilder $transferBuilder
     * @param Json            $serializer
     * @param Authorization   $authorization
     * @param null            $urlPath
     */
    public function __construct(
        protected readonly ConfigInterface $config,
        protected readonly TransferBuilder $transferBuilder,
        protected readonly Json            $serializer,
        private readonly Authorization     $authorization,
        protected readonly ?string         $urlPath = null
    ) {
    }

    /**
     * @return boolean
     */
    protected function isSandboxMode(): bool
    {
        return (bool)$this->config->getValue('sandbox_flag');
    }

    /**
     * @return Authorization
     */
    protected function getAuthorization(): Authorization
    {
        return $this->authorization;
    }
}
