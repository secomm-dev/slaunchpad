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
 * Interface ConverterInterface
 * @package Secomm\IntegrationBase\Model\Service\Http
 */
interface ConverterInterface
{
    /**
     * Converts response to ENV structure
     *
     * @param mixed $response
     * @return array
     * @throws \Secomm\IntegrationBase\Model\Service\Http\ConverterException
     */
    public function convert($response);
}
