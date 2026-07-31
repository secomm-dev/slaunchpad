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
 * Interface ConverterInterface
 * @package Secomm\GiaoHangNhanh\Model\Integration\Http
 */
interface ConverterInterface
{
    /**
     * Converts response to ENV structure
     *
     * @param mixed $response
     * @return array
     * @throws \Secomm\GiaoHangNhanh\Model\Integration\Http\ConverterException
     */
    public function convert($response);
}
