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

use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Magento\Framework\Exception\NoSuchEntityException;

class ServicesDataBuilder extends AbstractDataBuilder
{
    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        if ($this->getIsDevelopMode()) {
            $fromDistrict = 1457;
            $toDistrict = 1456;
        } else {
            $fromDistrict = '';
            $toDistrict = '';
        }

        $data = [
            self::TOKEN => $this->config->getValue('api_token'),
            self::FROM_DISTRICT => $fromDistrict,
            self::TO_DISTRICT => $toDistrict,
            self::SHOP_ID => (int)$this->config->getValue('shop_id')
        ];
        
        return $data;
    }
}
