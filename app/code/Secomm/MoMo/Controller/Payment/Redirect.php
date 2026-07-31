<?php
/**
 * MoMo redirect (start) controller.
 *
 * Builds the MoMo order server-side and redirects the browser to the MoMo
 * wallet payUrl.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Gateway\Helper\TransactionReader;

class Redirect extends Action implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * @var CommandPoolInterface
     */
    private CommandPoolInterface $commandPool;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var PaymentDataObjectFactory
     */
    private PaymentDataObjectFactory $paymentDataObjectFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param Context $context
     * @param CommandPoolInterface $commandPool
     * @param Session $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CommandPoolInterface $commandPool,
        Session $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->commandPool = $commandPool;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger = $logger;
    }

    /**
     * Create the MoMo order and redirect to the wallet.
     *
     * @return \Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            if (!$orderId) {
                return $this->_redirect('checkout/cart');
            }

            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();
            ContextHelper::assertOrderPayment($payment);

            $paymentDataObject = $this->paymentDataObjectFactory->create($payment);
            $result = $this->commandPool->get('get_pay_url')->execute([
                'payment' => $paymentDataObject,
                'amount' => $order->getTotalDue(),
            ]);

            $payUrl = TransactionReader::readPayUrl($result->get());
            if ($payUrl) {
                $this->getResponse()->setRedirect($payUrl);

                return;
            }
        } catch (\Exception $e) {
            $this->logger->error('MoMo redirect failed: ' . $e->getMessage(), ['exception' => get_class($e)]);
            $this->messageManager->addErrorMessage(__('MoMo payment could not be started. Please try again later.'));

            return $this->_redirect('checkout/cart/index');
        }

        return $this->_redirect('checkout/cart/index');
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
