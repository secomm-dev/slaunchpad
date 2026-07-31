<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\GiaoHangNhanh\Model\Integration\Http;

/**
 * Interface ClientInterface
 * @package Secomm\GiaoHangNhanh\Model\Integration\Http
 */
interface ClientInterface
{
    /**
     * Making request. Returns result as ENV array
     *
     * @param \Secomm\GiaoHangNhanh\Model\Integration\Http\TransferInterface $transferObject
     * @return array
     * @throws \Secomm\GiaoHangNhanh\Model\Integration\Http\ClientException
     * @throws \Secomm\GiaoHangNhanh\Model\Integration\Http\ConverterException
     */
    public function request(\Secomm\GiaoHangNhanh\Model\Integration\Http\TransferInterface $transferObject);
}
