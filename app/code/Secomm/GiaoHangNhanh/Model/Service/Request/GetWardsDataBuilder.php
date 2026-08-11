<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Request;

class GetWardsDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject)
    {
        return [
            self::TOKEN => $this->config->getValue('api_token'),
            self::DISTRICT_ID => $buildSubject['district_id']
        ];
    }
}