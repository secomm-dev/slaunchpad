<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
namespace Secomm\ZaloPay\Controller\Payment;

use Secomm\ZaloPay\Gateway\Helper\TransactionReader;
use Secomm\ZaloPay\Logger\Logger;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

class Ipn extends Action implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * @var CommandPoolInterface
     */
    private CommandPoolInterface $commandPool;

    /**
     * @var MethodInterface
     */
    private MethodInterface $method;

    /**
     * @var PaymentDataObjectFactory
     */
    private PaymentDataObjectFactory $paymentDataObjectFactory;

    /**
     * @var OrderFactory
     */
    private OrderFactory $orderFactory;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var SerializerJson
     */
    private SerializerJson $serializer;

    /**
     * Ipn constructor.
     *
     * @param Context $context
     * @param MethodInterface $method
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param OrderFactory $orderFactory
     * @param SerializerJson $serializer
     * @param CommandPoolInterface $commandPool
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        MethodInterface $method,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        OrderFactory $orderFactory,
        SerializerJson $serializer,
        CommandPoolInterface $commandPool,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->commandPool = $commandPool;
        $this->method = $method;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->orderFactory = $orderFactory;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * The message sent from Payment Service Provider (PSP) to Payment Service Consumer (PSC)
     * An example of this is closing the browser while Zalo pay is not redirected to payment success/failure
     *
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return;
        }
        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $data       = [
            'errors' => true,
            'messages' => __('Something went wrong white execute.')
        ];
        try {
            $response = $this->getRequest()->getContent();
            if ($response && is_string($response)) {
                $response               = $this->serializer->unserialize($response);
                $response['trans_data'] = $this->serializer->unserialize($response['data']);
            }
            $orderIncrementId = TransactionReader::readOrderId($response);
            $order            = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
            $payment          = $order->getPayment();
            ContextHelper::assertOrderPayment($payment);
            if ($payment->getMethod() === $this->method->getCode()
                && $order->getState() === Order::STATE_PENDING_PAYMENT) {
                $paymentDataObject = $this->paymentDataObjectFactory->create($payment);
                $this->commandPool->get('ipn')->execute(
                    [
                        'payment' => $paymentDataObject,
                        'response' => $response,
                        'is_ipn' => true,
                        'amount' => $order->getTotalDue()
                    ]
                );
                $data = [
                    'errors' => false,
                    'messages' => __('Success')
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->messageManager->addErrorMessage(__('Transaction has been declined. Please try again later.'));
            $resultJson->setHttpResponseCode(500);
        }

        return $resultJson->setData($data);
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return boolean|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
