<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Request;

/**
 * Class GetDistrictsDataBuilder
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Request
 */
class GetDistrictsDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject)
    {
        return [self::TOKEN => $this->config->getValue('api_token')];
    }
}