<?php
/**
 * Unit test for the MoMo ReturnAction controller.
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
use Secomm\MoMo\Controller\Payment\ReturnAction;
use Secomm\MoMo\Service\ReturnProcessor;

/**
 * Verifies the controller delegates to ReturnProcessor and maps its outcome
 * (success path / cart path / customer-safe exception) to a Redirect result,
 * adding the failure message only on the non-success paths.
 */
class ReturnActionTest extends TestCase
{
    /**
     * @var RequestInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private RequestInterface $request;

    /**
     * @var ManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private ManagerInterface $messageManager;

    /**
     * @var RedirectFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private RedirectFactory $redirectFactory;

    /**
     * @var Redirect|\PHPUnit\Framework\MockObject\MockObject
     */
    private Redirect $redirect;

    /**
     * @var ReturnProcessor|\PHPUnit\Framework\MockObject\MockObject
     */
    private ReturnProcessor $returnProcessor;

    /**
     * Path captured from the Redirect result.
     *
     * @var string|null
     */
    private ?string $redirectPath = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->returnProcessor = $this->createMock(ReturnProcessor::class);

        $this->redirect = $this->createMock(Redirect::class);
        $that = $this;
        $this->redirect->method('setPath')->willReturnCallback(
            function (string $path) use ($that) {
                $that->redirectPath = $path;

                return $this->redirect;
            }
        );

        $this->redirectFactory = $this->createMock(RedirectFactory::class);
        $this->redirectFactory->method('create')->willReturn($this->redirect);
    }

    /**
     * @return ReturnAction
     */
    private function createAction(): ReturnAction
    {
        return new ReturnAction(
            $this->request,
            $this->messageManager,
            $this->redirectFactory,
            $this->returnProcessor
        );
    }

    /**
     * Success path: Redirect result to the success page, no error message.
     *
     * @return void
     */
    public function testSuccessResultRedirectsToSuccessPageWithoutError(): void
    {
        $this->request->method('getParams')->willReturn(['resultCode' => 0, 'orderId' => '000000012']);
        $this->returnProcessor->method('process')->willReturn(ReturnProcessor::PATH_SUCCESS);
        $this->messageManager->expects($this->never())->method('addErrorMessage');

        $result = $this->createAction()->execute();

        $this->assertSame($this->redirect, $result);
        $this->assertSame(ReturnProcessor::PATH_SUCCESS, $this->redirectPath);
    }

    /**
     * Non-success path (non-zero resultCode): cart Redirect + error message.
     *
     * @return void
     */
    public function testFailureResultRedirectsToCartWithMessage(): void
    {
        $this->request->method('getParams')->willReturn(['resultCode' => 7000]);
        $this->returnProcessor->method('process')->willReturn(ReturnProcessor::PATH_CART);
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->createAction()->execute();

        $this->assertSame(ReturnProcessor::PATH_CART, $this->redirectPath);
    }

    /**
     * Processor exception (order not resolvable / not a MoMo order): the
     * customer-safe message is surfaced and the customer lands on the cart.
     *
     * @return void
     */
    public function testProcessorExceptionRedirectsToCartWithMessage(): void
    {
        $this->request->method('getParams')->willReturn(['resultCode' => 0]);
        $this->returnProcessor->method('process')
            ->willThrowException(new LocalizedException(__('MoMo payment could not be matched to an order.')));
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->createAction()->execute();

        $this->assertSame(ReturnProcessor::PATH_CART, $this->redirectPath);
    }
}
