<?php
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Boolfly Integration
 */
namespace Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http;

/**
 * Interface ClientInterface
 * @package Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http
 */
interface ClientInterface
{
    /**
     * Making request. Returns result as ENV array
     *
     * @param \Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\TransferInterface $transferObject
     * @return array
     * @throws \Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\ClientException
     * @throws \Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\ConverterException
     */
    public function request(\Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\TransferInterface $transferObject);
}
