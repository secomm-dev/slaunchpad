<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Integration\Controller\Payment;

use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Magento\TestFramework\TestCase\AbstractController;

class IpnTest extends AbstractController
{

    /**
     * @return void
     */
    public function testExecute()
    {
        $dataTest  = [
            AbstractDataBuilder::TRANS_DATA => [
                \Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder::APP_TRANS_ID => 'incrementId_000000194',
            ]
        ];
        $this->getRequest()
            ->setMethod('POST')
            ->setContent($dataTest);

        $this->dispatch('zalopay/payment/ipn');
        $result = json_decode($this->getResponse()->getBody(), 1);
        $this->assertTrue($result['errors']);
    }
}
