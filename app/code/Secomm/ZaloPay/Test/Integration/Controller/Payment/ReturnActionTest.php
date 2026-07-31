<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Test\Integration\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Framework\App\Request\Http;

class ReturnActionTest extends AbstractController
{

    const ORDER_ID = 192;

    /**
     * @var \Secomm\ZaloPay\Controller\Payment\ReturnAction::execute
     * @return void
     */
    public function testExecute()
    {
        $this->_objectManager->removeSharedInstance(Session::class);
        $session = $this->_objectManager->get(Session::class);
        $this->_objectManager->removeSharedInstance(Session::class);
        $session->setLastOrderId(self::ORDER_ID);
        $dataTest  = [
            "amount" => "932549",
            "appid" => "3378",
            "apptransid" => "240129_000000195",
            "bankcode" => "",
            "checksum" => "7837c70369fb2b54ab41cf2737696a6d5b9553dcd7b61e3925a7c98a90581e0a",
            "discountamount" => "0",
            "pmcid" => "38",
            "status" => "1"
        ];
        $request = $this->_objectManager->get(Http::class);
        $session->setLastOrderId(self::ORDER_ID);
        $request->setParams($dataTest);
        $request->setMethod(Http::METHOD_POST);
        $request->setContent(http_build_query($dataTest));

        $this->dispatch('zalopay/payment/returnaction');
        $this->assertRedirect($this->stringContains('checkout/onepage/success'));
    }
}
