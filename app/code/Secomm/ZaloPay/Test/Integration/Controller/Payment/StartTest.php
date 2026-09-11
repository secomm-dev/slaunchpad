<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Integration\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * TASK-EDS9T5 (payment-first only): the Start action builds the redirect
 * from the ACTIVE QUOTE — never from an order. Without a payable quote
 * (empty session) it must fail safely: no order, no attempt, the customer
 * is redirected back to the cart with an error.
 */
class StartTest extends AbstractController
{
    /**
     * @return void
     */
    public function testExecuteWithoutPayableQuoteFailsSafelyToCart(): void
    {
        $this->_objectManager->removeSharedInstance(Session::class);
        $this->_objectManager->get(Session::class);

        $this->getRequest()->setMethod('POST');
        $this->dispatch('zalopay/payment/start');

        $this->assertRedirect($this->stringContains('checkout/cart/index'));
    }
}
