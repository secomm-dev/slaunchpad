<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
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