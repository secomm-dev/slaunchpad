<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\IntegrationBase\Model\Service\Http;

/**
 * Interface ClientInterface
 * @package Secomm\IntegrationBase\Model\Service\Http
 */
interface ClientInterface
{
    /**
     * Making request. Returns result as ENV array
     *
     * @param \Secomm\IntegrationBase\Model\Service\Http\TransferInterface $transferObject
     * @return array
     * @throws \Secomm\IntegrationBase\Model\Service\Http\ClientException
     * @throws \Secomm\IntegrationBase\Model\Service\Http\ConverterException
     */
    public function request(\Secomm\IntegrationBase\Model\Service\Http\TransferInterface $transferObject);
}
