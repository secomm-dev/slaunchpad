<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
namespace Secomm\ZaloPay\Gateway\Http;

use Magento\Payment\Gateway\Http\Transfer;
use Magento\Payment\Gateway\Http\TransferInterface;
use Secomm\ZaloPay\Model\Config;

class TransferFactory extends AbstractTransferFactory
{
    /**
     * @inheritdoc
     */
    public function create(array $request): TransferInterface|Transfer
    {
        return $this->transferBuilder
            ->setMethod('POST')
            ->setHeaders($this->getAuthorization()->getHeaders())
            ->setBody($request)
            ->setUri($this->getUrl())
            ->build();
    }

    /**
     * Get Url
     *
     * @return string
     */
    private function getUrl(): string
    {
        $urlPayment = $this->isSandboxMode() ? Config::SANDBOX_PAYMENT_ZALO_URL : Config::LIVE_PAYMENT_ZALO_URL;
        return $urlPayment . $this->urlPath;
    }
}
