<?php
/**
 * MoMo Return (browser redirect) controller.
 *
 * Lenient: the authoritative confirmation is the Notify (IPN). Here we only
 * look at MoMo's resultCode to decide success/failure page — and on success,
 * delegate to ReturnProcessor so the checkout success session is rebuilt
 * from the order MoMo reports (orderId = increment id), never assumed from
 * whatever the browser session happens to hold.
 *
 * Composition over inheritance: implements the HTTP-method interface directly
 * (no deprecated Action base class) and injects only what it uses.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Secomm\MoMo\Service\ReturnProcessor;

/**
 * MoMo Return (browser redirect) controller.
 */
class ReturnAction implements HttpGetActionInterface
{
    public const RESULT_CODE = ReturnProcessor::RESULT_CODE;

    /**
     * ReturnAction constructor.
     *
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $redirectFactory
     * @param ReturnProcessor $returnProcessor
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ManagerInterface $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly ReturnProcessor $returnProcessor
    ) {
    }

    /**
     * Redirect the customer based on MoMo's resultCode.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        try {
            $path = $this->returnProcessor->process($this->request->getParams());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->redirectTo(ReturnProcessor::PATH_CART);
        }

        if ($path !== ReturnProcessor::PATH_SUCCESS) {
            $this->messageManager->addErrorMessage(
                __('MoMo payment was not completed. Please try again.')
            );
        }

        return $this->redirectTo($path);
    }

    /**
     * Build a redirect result for a Magento path.
     *
     * @param string $path
     * @return Redirect
     */
    private function redirectTo(string $path): Redirect
    {
        return $this->redirectFactory->create()->setPath($path);
    }
}
