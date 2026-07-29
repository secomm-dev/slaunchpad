<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   Giao hang nhanh
 */
namespace Boolfly\GiaoHangNhanh\Model\Service\Request;

use Boolfly\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Magento\Framework\Exception\NoSuchEntityException;

class CancelOrderDataBuilder extends AbstractDataBuilder
{
    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        $order = SubjectReader::readOrder($buildSubject);
        $orderCodes = [$order->getData('tracking_code')];

        return [
            self::TOKEN => $this->config->getValue('api_token'),
            self::ORDER_CODES => $orderCodes
        ];
    }
}
