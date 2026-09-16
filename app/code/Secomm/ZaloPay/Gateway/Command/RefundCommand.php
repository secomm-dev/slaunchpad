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

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapperInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
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
     * TASK-CG6BM7: only LocalizedException is ever thrown (Payment::refund
     * catches exactly that type — a generic Exception used to escape raw).
     * Customer-facing messages come ONLY from the provider-status map
     * (RefundProcessor) or a generic fallback — raw transport/internal
     * exception text is never surfaced, only logged.
     *
     * @param array $commandSubject
     * @return void
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException
     * @throws LocalizedException
     */
    public function execute(array $commandSubject): void
    {
        $refundTransactionFactory = $this->refundTransactionInterfaceFactory->create();
        $response = null;
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
            $statusCode = $this->readReturnCode($response);
            if ($statusCode === AbstractResponseValidator::REFUND_PROCESSING) {
                $requestDataQuery = $this->refundQueryCommand->setZaloRefundId($this->mRefundId)
                    ->buildRequestData($commandSubject);
                $response = $this->refundQueryCommand->getRefundQuery($requestDataQuery);
                $statusCode = $this->readReturnCode($response);
            }

            if ($statusCode === AbstractResponseValidator::RETURN_CODE_ACCEPT
                || $statusCode === AbstractResponseValidator::REFUND_PROCESSING
            ) {
                $refundTransactionFactory->setIsProcessed(RefundInterface::PROCESSED);
                $statusMessage = $statusCode === AbstractResponseValidator::RETURN_CODE_ACCEPT
                    ? RefundProcessor::processRefundStatus(AbstractResponseValidator::RETURN_CODE_ACCEPT)
                    : RefundProcessor::processRefundStatus(AbstractResponseValidator::REFUND_PROCESSING);
                $this->messageManager->addSuccessMessage(self::PREFIX_ZALO_PAY_MESSAGE . __($statusMessage));
            } else {
                // FAIL (2) or protocol anomaly (missing/unknown return_code):
                // explicit, provider-map-based failure — never a false success.
                $this->throwProviderFailure($response, $statusCode);
            }

            $this->handler?->handle(
                $commandSubject,
                $responseRefund
            );
        } catch (LocalizedException $exception) {
            // Validation/protocol failures already carry a customer-safe
            // message — preserve it (logged for evidence).
            $this->logger->error(
                'ZaloPay refund failed: ' . $exception->getMessage(),
                ['m_refund_id' => $this->mRefundId]
            );
            throw $exception;
        } catch (\Exception $exception) {
            // Transport/internal failure: the raw message is internal-only.
            $this->logger->error(
                'ZaloPay refund transport failure: ' . $exception->getMessage(),
                ['m_refund_id' => $this->mRefundId]
            );
            $subCode = $this->readSubReturnCode($response);
            $statusMessage = $subCode !== null
                ? RefundProcessor::processRefundStatus($subCode)
                : (string)__('Refund failed. Please try again later.');
            throw new LocalizedException(__(self::PREFIX_ZALO_PAY_MESSAGE . $statusMessage));
        } finally {
            // PROCESSING is the only state that persists the pending refund
            // row (driving the bounded RefundCronjob queries) and moves the
            // creditmemo to PROCESSING. The direct $creditMemo->save() is
            // load-bearing: it assigns the creditmemo entity_id the refund
            // row's FK needs — the creditmemo has not been persisted yet at
            // this point in the CreditmemoService::refund flow.
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
     * Safe read of the provider return_code: a missing or non-numeric code
     * is a protocol anomaly, never silently coerced.
     *
     * @param array|null $response
     * @return int|null
     */
    private function readReturnCode(?array $response): ?int
    {
        if ($response === null) {
            return null;
        }
        $code = $response[AbstractResponseValidator::RETURN_CODE] ?? null;

        return is_numeric($code) ? (int)$code : null;
    }

    /**
     * Safe read of the provider sub_return_code (fail detail).
     *
     * @param array|null $response
     * @return int|null
     */
    private function readSubReturnCode(?array $response): ?int
    {
        if ($response === null) {
            return null;
        }
        $code = $response[AbstractResponseValidator::SUB_RETURN_CODE] ?? null;

        return is_numeric($code) ? (int)$code : null;
    }

    /**
     * Explicit provider-side failure: map the CURRENT response's
     * sub_return_code through the safe status map (never the stale
     * pre-query response, never raw provider/transport text) and throw a
     * LocalizedException — the exception type Payment::refund catches.
     *
     * @param array|null $response
     * @param int|null $statusCode
     * @return void
     * @throws LocalizedException
     */
    private function throwProviderFailure(?array $response, ?int $statusCode): void
    {
        $subCode = $this->readSubReturnCode($response);
        if ($subCode !== null) {
            $statusMessage = RefundProcessor::processRefundStatus($subCode);
        } elseif ($statusCode === AbstractResponseValidator::REFUND_FAIL) {
            $statusMessage = RefundProcessor::processRefundStatus(AbstractResponseValidator::REFUND_FAIL);
        } else {
            $statusMessage = (string)__('Refund failed. Please try again later.');
        }
        $this->logger->error(
            sprintf(
                'ZaloPay refund refused by provider: return_code=%s sub_return_code=%s',
                var_export($statusCode, true),
                var_export($subCode, true)
            ),
            ['m_refund_id' => $this->mRefundId]
        );
        throw new LocalizedException(__(self::PREFIX_ZALO_PAY_MESSAGE . $statusMessage));
    }

    /**
     * @param array $commandSubject
     * @return array
     */
    public function buildRequestData(array $commandSubject): array
    {
        return $this->requestBuilder->build($commandSubject);
    }
}
