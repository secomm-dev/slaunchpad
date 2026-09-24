<?php
/**
 * MoMo Return (browser redirect) controller — payment-first (MOMO-01).
 *
 * The browser is never payment proof (AC7): ReturnProcessor verifies
 * authoritatively via the server-side v2/query, drives the idempotent
 * OrderFinalizer (the IPN may have finalized first — the bound order is
 * recovered, never duplicated) and rebuilds the checkout success session
 * (AC8).
 *
 * Composition over inheritance: implements the HTTP-method interface
 * directly (no deprecated Action base class) and injects only what it uses.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
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
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Service\ReturnProcessor;

/**
 * MoMo Return (browser redirect) controller — thin delegate.
 */
class ReturnAction implements HttpGetActionInterface
{
    /**
     * ReturnAction constructor.
     *
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $redirectFactory
     * @param LoggerInterface $logger
     * @param ReturnProcessor $returnProcessor
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ManagerInterface $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly LoggerInterface $logger,
        private readonly ReturnProcessor $returnProcessor
    ) {
    }

    /**
     * Dispatch the MoMo browser return redirect.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $params = $this->request->getParams();
        $orderRef = trim((string)($params['orderId'] ?? ''));
        if ($orderRef === '') {
            $this->logger->warning('MoMo return without an order reference.', ['params' => $params]);
            $this->messageManager->addErrorMessage(
                __('MoMo payment session not found. Please contact support.')
            );

            return $this->redirectTo('checkout/cart/index');
        }

        try {
            return $this->redirectTo($this->returnProcessor->process($params));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->redirectTo('checkout/cart/index');
        } catch (\Exception $e) {
            $this->logger->error(
                'MoMo return action failed: ' . $e->getMessage(),
                [
                    'exception' => get_class($e),
                    'order_ref' => $orderRef,
                    'trace' => $e->getTraceAsString(),
                ]
            );
            $this->messageManager->addErrorMessage(__('Transaction has been declined. Please try again later.'));

            return $this->redirectTo('checkout/cart/index');
        }
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
