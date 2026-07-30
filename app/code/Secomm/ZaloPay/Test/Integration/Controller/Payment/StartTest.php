<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Test\Integration\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\TestCase\AbstractController;

class StartTest extends AbstractController
{

    public function testExecute()
    {
        $this->_objectManager->removeSharedInstance(Session::class);
        $session = $this->_objectManager->get(Session::class);
        $session->setLastOrderId(ReturnActionTest::ORDER_ID);
        $dataTest  = [
            'test'
        ];
        $this->getRequest()
            ->setMethod('POST')
            ->setParams($dataTest);
        $this->dispatch('zalopay/payment/start');
        $this->assertRedirect($this->stringContains('checkout/cart/index'));
    }
}
