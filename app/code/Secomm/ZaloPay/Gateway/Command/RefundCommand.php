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

namespace Secomm\ZaloPay\Gateway\Command;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapperInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Api\Data\RefundInterfaceFactory;
use Secomm\ZaloPay\Command\Refund\SaveCommand;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Helper\RefundProcessor;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Plugin\Model\Order\CreditmemoPlugin;

use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class InitializeCommand
 *
 */
class RefundCommand implements CommandInterface
{
    const PREFIX_ZALO_PAY_MESSAGE = 'Zalopay: ';

    /**
     * @var mixed|string
     */
    private mixed $mRefundId = '';

    /**
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param Logger $logger
     * @param RefundQueryCommand $refundQueryCommand
     * @param SaveCommand $saveCommand
     * @param RefundInterfaceFactory $refundTransactionInterfaceFactory
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     * @param Rate $rate
     * @param Json $serializer
     * @param HandlerInterface|null $handler
     * @param ValidatorInterface|null $validator
     * @param ErrorMessageMapperInterface|null $errorMessageMapper
     */
    public function __construct(
        private readonly BuilderInterface           $requestBuilder,
        private readonly TransferFactoryInterface   $transferFactory,
        private readonly ClientInterface            $client,
        private readonly Logger                     $logger,
        protected RefundQueryCommand                $refundQueryCommand,
        protected SaveCommand                       $saveCommand,
        protected RefundInterfaceFactory            $refundTransactionInterfaceFactory,
        protected RequestInterface                  $request,
        protected ManagerInterface                  $messageManager,
        protected Rate                              $rate,
        private readonly Json                       $serializer,
        private ?HandlerInterface                   $handler = null,
        private ?ValidatorInterface                 $validator = null,
        private ?ErrorMessageMapperInterface        $errorMessageMapper = null
    ) {
    }

    /**
     * Zalo Pay's response Status PROCESSING and SUCCESS will create Credit Memos Refund item with status Refunded
     * Zalo Pay's refund is processed asynchronously, so you need to call the api to check the refund status through query_refund api.
     *
     * @param array $commandSubject
     * @return void
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException|Exception
     */
    public function execute(array $commandSubject): void
    {
        $refundTransactionFactory = $this->refundTransactionInterfaceFactory->create();
        $response = null;
        $isThrowException = false;
        $statusCode = null;
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $payment = $paymentDO->getPayment();
        $creditMemo = $payment->getCreditmemo();
        $requestData = $this->buildRequestData($commandSubject);
        $requestDataQuery = [];
        try {
            $transferO = $this->transferFactory->create(
                $requestData
            );
            $amount = round((float)SubjectReader::readAmount($commandSubject), 2);
            $amount = (int)$this->rate->getVndAmount($payment->getOrder(), $amount);

            $orderId = (int)$creditMemo->getOrder()->getId();
            $this->mRefundId = $transferO->getBody()[RefundInterface::M_REFUND_ID];
            $refundTransactionFactory->setMRefundId($this->mRefundId);
            $refundTransactionFactory->setOrderId($orderId);
            $refundTransactionFactory->setIsProcessed(RefundInterface::NOT_PROCESSED);
            $refundTransactionFactory->setAmount($amount);

            $response = $this->client->placeRequest($transferO);
            $responseRefund = $response;
            $statusCode = $response[AbstractResponseValidator::RETURN_CODE];
            if ($statusCode === AbstractResponseValidator::REFUND_PROCESSING) {
                $requestDataQuery = $this->refundQueryCommand->setZaloRefundId($this->mRefundId)
                    ->buildRequestData($commandSubject);
                $response = $this->refundQueryCommand->getRefundQuery($requestDataQuery);
                $statusCode = $response[AbstractResponseValidator::RETURN_CODE];
            }

            /**
             * Status processing and success will create Credit Memos Refund item with status refunded
             * If isThrowException = true that cannot create Credit Memos Refund item and the zalo_pay_refund table also
             */
            if ($statusCode < AbstractResponseValidator::RETURN_CODE_ACCEPT
                || $statusCode === AbstractResponseValidator::REFUND_FAIL) {
                $isThrowException = true;
            }

            if ($statusCode === AbstractResponseValidator::RETURN_CODE_ACCEPT
                || $statusCode === AbstractResponseValidator::REFUND_PROCESSING
            ) {
                $refundTransactionFactory->setIsProcessed(RefundInterface::PROCESSED);
                $statusMessage = RefundProcessor::processRefundStatus(
                    $response[AbstractResponseValidator::SUB_RETURN_CODE] ?? $statusCode
                );
                $this->messageManager->addSuccessMessage(self::PREFIX_ZALO_PAY_MESSAGE . __($statusMessage));
            } else {
                if ($this->validator !== null) {
                    $result = $this->validator->validate(
                        array_merge($commandSubject, ['response' => $response])
                    );
                    if (!$result->isValid()) {
                        $this->processErrors($result);
                    }
                }
            }

            $this->handler?->handle(
                $commandSubject,
                $responseRefund
            );
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
            if (!$isThrowException && isset($response[AbstractResponseValidator::RESPONSE_MESSAGE])) {
                $this->messageManager->addErrorMessage(self::PREFIX_ZALO_PAY_MESSAGE .
                    __(RefundProcessor::processRefundStatus($response[AbstractResponseValidator::SUB_RETURN_CODE])));
            } else {
                if ($isThrowException) {
                    if (isset($response[AbstractResponseValidator::RESPONSE_MESSAGE])) {
                        throw new Exception(self::PREFIX_ZALO_PAY_MESSAGE .
                            __(RefundProcessor::processRefundStatus($response[AbstractResponseValidator::SUB_RETURN_CODE])));
                    } else {
                        $this->messageManager->addErrorMessage(__($exception->getMessage()));
                    }
                } else {
                    $this->messageManager->addErrorMessage(__($exception->getMessage()));
                }
            }
        } finally {
            //TODO change condition
            if ($statusCode === AbstractResponseValidator::REFUND_PROCESSING) {
                //status is processing
                $creditMemo->setState(CreditmemoPlugin::STATE_PROCESSING);
                $refundTransactionFactory->setIsProcessed(RefundInterface::NOT_PROCESSED);
                $creditMemo->save();
                $refundTransactionFactory->setAdditionalInformation($this->serializer->serialize($requestDataQuery));
                $refundTransactionFactory->setCreditMemoId((int)$creditMemo->getId());
                $this->saveCommand->execute($refundTransactionFactory);
            }
        }
    }

    /**
     * @param array $commandSubject
     * @return array
     */
    public function buildRequestData(array $commandSubject): array
    {
        return $this->requestBuilder->build($commandSubject);
    }

    /**
     * Tries to map error messages from validation result and logs processed message.
     * Throws an exception with mapped message or default error.
     *
     * @param ResultInterface $result
     * @throws CommandException
     */
    private function processErrors(ResultInterface $result)
    {
        $messages = [];
        $errorsSource = array_merge($result->getErrorCodes(), $result->getFailsDescription());
        foreach ($errorsSource as $errorCodeOrMessage) {
            $errorCodeOrMessage = (string)$errorCodeOrMessage;

            // error messages mapper can be not configured if payment method doesn't have custom error messages.
            if ($this->errorMessageMapper !== null) {
                $mapped = (string)$this->errorMessageMapper->getMessage($errorCodeOrMessage);
                if (!empty($mapped)) {
                    $messages[] = $mapped;
                    $errorCodeOrMessage = $mapped;
                }
            }
            $this->logger->critical('Payment Error: ' . $errorCodeOrMessage);
        }

        throw new CommandException(
            !empty($messages) ? __(implode(PHP_EOL, $messages)) : __('Transaction has been declined. Please try again later.')
        );
    }
}
