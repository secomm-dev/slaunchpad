<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Integration\Controller\Payment;

use Magento\Framework\App\Request\Http;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * TASK-EDS9T5 (payment-first only): the browser return is NEVER payment
 * proof — it is UX + authoritative v2/query verification. These hermetic
 * cases cover the failure entry points (no reference, unknown reference);
 * both keep the cart and never create an order.
 */
class ReturnActionTest extends AbstractController
{
    /**
     * A return without any app transaction reference fails safely to cart.
     *
     * @return void
     */
    public function testExecuteWithoutReferenceRedirectsToCart(): void
    {
        $request = $this->_objectManager->get(Http::class);
        $request->setParams([]);
        $request->setMethod(Http::METHOD_GET);

        $this->dispatch('zalopay/payment/returnaction');

        $this->assertRedirect($this->stringContains('checkout/cart/index'));
    }

    /**
     * A return referencing an unknown payment attempt fails safely to cart
     * (no order is created for an unverified reference).
     *
     * @return void
     */
    public function testExecuteWithUnknownReferenceRedirectsToCart(): void
    {
        $request = $this->_objectManager->get(Http::class);
        $request->setParams([
            'apptransid' => '240129_000000000',
            'status' => '1',
        ]);
        $request->setMethod(Http::METHOD_GET);

        $this->dispatch('zalopay/payment/returnaction');

        $this->assertRedirect($this->stringContains('checkout/cart/index'));
    }
}
