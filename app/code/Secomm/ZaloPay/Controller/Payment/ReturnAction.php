<?php
/***********************************************************************
 * *
 *  *
 *  * @copyright Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  * @author    Secomm Teams
 * *
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Service\ReturnProcessor;

/**
 * Return (browser redirect) action — payment-first, the ONLY production flow.
 *
 * The browser is never payment proof: ReturnProcessor verifies
 * authoritatively via the provider v2/query, drives the idempotent
 * OrderFinalizer (the IPN may have finalized first — the bound order is
 * recovered, never duplicated) and rebuilds the checkout success session.
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
        private readonly RedirectFactory  $redirectFactory,
        private readonly LoggerInterface  $logger,
        private readonly ReturnProcessor  $returnProcessor
    ) {
    }

    /**
     * Dispatch the ZaloPay browser return redirect.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $params = $this->request->getParams();
        $appTransId = trim((string)($params['apptransid'] ?? $params['app_trans_id'] ?? ''));
        if ($appTransId === '') {
            $this->logger->warning('ZaloPay return without an app transaction reference.', [
                'params' => $params,
            ]);
            $this->messageManager->addErrorMessage(
                __('ZaloPay payment session not found. Please contact support.')
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
                'ZaloPay return action failed: ' . $e->getMessage(),
                [
                    'exception' => get_class($e),
                    'app_trans_id' => $appTransId,
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
