<?php
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Boolfly Integration
 */
namespace Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http;

/**
 * Interface ConverterInterface
 * @package Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http
 */
interface ConverterInterface
{
    /**
     * Converts response to ENV structure
     *
     * @param mixed $response
     * @return array
     * @throws \Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\ConverterException
     */
    public function convert($response);
}
