<?php
/**
 * Unit test for the MoMo Return controller (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Controller\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Controller\Payment\ReturnAction;
use Secomm\MoMo\Service\ReturnProcessor;

/**
 * Verifies the thin controller contract: the ReturnProcessor owns every
 * payment decision, the customer only ever sees customer-safe messages and
 * every failure lands on the cart page (AC7 UX surface).
 */
class ReturnActionTest extends TestCase
{
    private RequestInterface&\PHPUnit\Framework\MockObject\MockObject $request;

    private ManagerInterface&\PHPUnit\Framework\MockObject\MockObject $messageManager;

    private Redirect&\PHPUnit\Framework\MockObject\MockObject $redirect;

    private ReturnProcessor&\PHPUnit\Framework\MockObject\MockObject $returnProcessor;

    private ReturnAction $controller;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $this->redirect = $this->createMock(Redirect::class);
        $redirectFactory->method('create')->willReturn($this->redirect);
        $this->returnProcessor = $this->createMock(ReturnProcessor::class);

        $this->controller = new ReturnAction(
            $this->request,
            $this->messageManager,
            $redirectFactory,
            $this->createMock(LoggerInterface::class),
            $this->returnProcessor
        );

        $this->redirect->method('setPath')->willReturnSelf();
    }

    /**
     * A return without an order reference is a customer-safe cart bounce.
     *
     * @return void
     */
    public function testMissingOrderRefRedirectsToCart(): void
    {
        $this->request->method('getParams')->willReturn([]);
        $this->returnProcessor->expects($this->never())->method('process');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->redirect->expects($this->once())->method('setPath')->with('checkout/cart/index');

        $this->assertSame($this->redirect, $this->controller->execute());
    }

    /**
     * The processor's path (the success page after an authoritative
     * finalization) is where the customer is redirected.
     *
     * @return void
     */
    public function testSuccessPathRedirectsToSuccessPage(): void
    {
        $this->request->method('getParams')->willReturn(['orderId' => 'MOMOREF']);
        $this->returnProcessor->method('process')->willReturn('checkout/onepage/success');
        $this->messageManager->expects($this->never())->method('addErrorMessage');

        $this->redirect->expects($this->once())->method('setPath')->with('checkout/onepage/success');

        $this->assertSame($this->redirect, $this->controller->execute());
    }

    /**
     * A customer-safe LocalizedException surfaces its message on the cart
     * page — never a stack trace, never a success page.
     *
     * @return void
     */
    public function testLocalizedExceptionRedirectsToCartWithMessage(): void
    {
        $this->request->method('getParams')->willReturn(['orderId' => 'MOMOREF']);
        $this->returnProcessor->method('process')->willThrowException(
            new LocalizedException(__('Your MoMo payment was not completed.'))
        );
        $this->messageManager->expects($this->once())->method('addErrorMessage')
            ->with('Your MoMo payment was not completed.');

        $this->redirect->expects($this->once())->method('setPath')->with('checkout/cart/index');

        $this->assertSame($this->redirect, $this->controller->execute());
    }

    /**
     * Any technical failure is logged and downgraded to a generic customer
     * message on the cart page.
     *
     * @return void
     */
    public function testTechnicalFailureRedirectsToCartWithGenericMessage(): void
    {
        $this->request->method('getParams')->willReturn(['orderId' => 'MOMOREF']);
        $this->returnProcessor->method('process')->willThrowException(new \RuntimeException('DB gone'));
        $this->messageManager->expects($this->once())->method('addErrorMessage')
            ->with('Transaction has been declined. Please try again later.');

        $this->redirect->expects($this->once())->method('setPath')->with('checkout/cart/index');

        $this->assertSame($this->redirect, $this->controller->execute());
    }
}
