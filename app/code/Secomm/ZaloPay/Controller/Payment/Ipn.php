<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Controller\Payment;

use Secomm\ZaloPay\Gateway\Helper\TransactionReader;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Service\IpnProcessor;
use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\HttpGetActionInterface;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * ZaloPay IPN (server-to-server callback) controller — composition style.
 */
class Ipn implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Ipn constructor.
     *
     * @param Http $request
     * @param JsonFactory $resultJsonFactory
     * @param ManagerInterface $messageManager
     * @param MethodInterface $method
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SerializerJson $serializer
     * @param CommandPoolInterface $commandPool
     * @param Logger $logger
     * @param IpnProcessor $ipnProcessor
     */
    public function __construct(
        private readonly Http $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ManagerInterface $messageManager,
        private readonly MethodInterface $method,
        private readonly PaymentDataObjectFactory $paymentDataObjectFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SerializerJson $serializer,
        private readonly CommandPoolInterface $commandPool,
        private readonly Logger $logger,
        private readonly IpnProcessor $ipnProcessor
    ) {
    }

    /**
     * Handle the ZaloPay server-to-server IPN callback.
     *
     * The message sent from Payment Service Provider (PSP) to Payment Service Consumer (PSC).
     * An example of this is closing the browser while Zalo pay is not redirected to payment success/failure
     *
     * @return Json|null
     */
    public function execute(): ?Json
    {
        if (!$this->request->isPost()) {
            return null;
        }
        $rawContent = (string)$this->request->getContent();
        $this->logger->info(
            'ZaloPay IPN Hit. Content: ' . $rawContent
            . ' Params: ' . json_encode($this->request->getParams())
        );
        $resultJson = $this->resultJsonFactory->create();
        $data       = [
            'errors' => true,
            'messages' => __('Something went wrong white execute.')
        ];
        try {
            $response = [];
            if ($rawContent !== '') {
                try {
                    $response = $this->serializer->unserialize($rawContent);
                } catch (\Exception $e) {
                    $response = $this->request->getParams();
                }
            } else {
                $response = $this->request->getParams();
            }

            if (isset($response['data']) && is_string($response['data'])) {
                $response['trans_data'] = $this->serializer->unserialize($response['data']);
            }

            $this->logger->info('ZaloPay IPN Parsed Response: ' . json_encode($response));

            // Payment-first: attempt-first lookup. An IPN arriving BEFORE the
            // Return action (order not yet placed) is a valid lifecycle state —
            // the processor marks the attempt PAID and answers 200, not 404.
            // null = payload references no payment attempt -> legacy flow below.
            $paymentFirstResult = $this->ipnProcessor->process($response);
            if ($paymentFirstResult !== null) {
                if ($paymentFirstResult['http_code'] !== 200) {
                    $resultJson->setHttpResponseCode($paymentFirstResult['http_code']);
                }

                return $resultJson->setData(
                    [
                        'errors' => $paymentFirstResult['errors'],
                        'messages' => __($paymentFirstResult['messages'])
                    ]
                );
            }

            $orderIncrementId = TransactionReader::readOrderId($response);
            $order            = $this->loadOrderByIncrementId($orderIncrementId);
            if ($order === null) {
                $this->logger->error('ZaloPay IPN Order Not Found: ' . $orderIncrementId);
                $resultJson->setHttpResponseCode(404);
                $data = ['errors' => true, 'messages' => __('Order not found.')];

                return $resultJson->setData($data);
            }
            $payment          = $order->getPayment();
            ContextHelper::assertOrderPayment($payment);
            $this->logger->info(sprintf(
                'ZaloPay IPN Order #%s payment method: %s (expected: %s), order state: %s',
                $orderIncrementId,
                $payment->getMethod(),
                $this->method->getCode(),
                $order->getState()
            ));
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
                $this->logger->info('ZaloPay IPN Command executed successfully for order #' . $orderIncrementId);
                $data = [
                    'errors' => false,
                    'messages' => __('Success')
                ];
            } else {
                $this->logger->warning(sprintf(
                    'ZaloPay IPN condition not met for order #%s. Payment method: %s, State: %s',
                    $orderIncrementId,
                    $payment->getMethod(),
                    $order->getState()
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error('ZaloPay IPN Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->messageManager->addErrorMessage(__('Transaction has been declined. Please try again later.'));
            $resultJson->setHttpResponseCode(500);
        }

        return $resultJson->setData($data);
    }

    /**
     * Load an order by increment id via the repository (no deprecated ->load()).
     *
     * @param string $incrementId
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    private function loadOrderByIncrementId(string $incrementId)
    {
        if ($incrementId === '') {
            return null;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId)
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        return $orders ? reset($orders) : null;
    }

    /**
     * Create exception in case CSRF validation failed.
     *
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     *
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     * @return boolean|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
