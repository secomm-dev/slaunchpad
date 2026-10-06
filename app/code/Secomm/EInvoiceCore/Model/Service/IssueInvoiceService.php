<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceCore\Api\OrderStoreIdProviderInterface;
use Secomm\EInvoiceCore\Model\AdjustmentRequestBuilderPool;
use Secomm\EInvoiceCore\Model\CommercialDiscountRequestBuilderPool;
use Secomm\EInvoiceCore\Model\Config;
use Secomm\EInvoiceCore\Model\InvoiceDocumentServicePool;
use Secomm\EInvoiceCore\Model\InvoiceIssuerPool;
use Secomm\EInvoiceCore\Model\IssueRequestBuilderPool;
use Secomm\EInvoiceLog\Api\IssueLogRepositoryInterface;
use Secomm\EInvoiceLog\Model\IssueLog;
use Secomm\EInvoiceLog\Model\IssueLogFactory;
use Secomm\EInvoiceCore\Api\OriginInvoiceResolverInterface;

/**
 * Orchestrates synchronous electronic invoice issuance and persists attempt logs.
 */
class IssueInvoiceService
{
    /**
     * @param Config $config
     * @param OrderStoreIdProviderInterface $orderStoreIdProvider
     * @param IssueRequestBuilderPool $requestBuilderPool
     * @param InvoiceIssuerPool $invoiceIssuerPool
     * @param InvoiceDocumentServicePool $documentServicePool
     * @param AdjustmentRequestBuilderPool $adjustmentBuilderPool
     * @param CommercialDiscountRequestBuilderPool $commercialDiscountBuilderPool
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param IssueLogFactory $issueLogFactory
     * @param IssueLogRepositoryInterface $issueLogRepository
     * @param Json $json
     * @param DateTime $dateTime
     * @param InvSeriesPublishLock $invSeriesPublishLock
     * @param PublishRetryPolicy $publishRetryPolicy
     * @param OriginInvoiceResolverInterface $originInvoiceResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly OrderStoreIdProviderInterface $orderStoreIdProvider,
        private readonly IssueRequestBuilderPool $requestBuilderPool,
        private readonly InvoiceIssuerPool $invoiceIssuerPool,
        private readonly InvoiceDocumentServicePool $documentServicePool,
        private readonly AdjustmentRequestBuilderPool $adjustmentBuilderPool,
        private readonly CommercialDiscountRequestBuilderPool $commercialDiscountBuilderPool,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly IssueLogFactory $issueLogFactory,
        private readonly IssueLogRepositoryInterface $issueLogRepository,
        private readonly InvSeriesPublishLock $invSeriesPublishLock,
        private readonly PublishRetryPolicy $publishRetryPolicy,
        private readonly OriginInvoiceResolverInterface $originInvoiceResolver,
        private readonly Json $json,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Issue invoice for order synchronously (shipment observer or internal call).
     *
     * @param int $orderId
     * @param bool $force
     * @param array<string, mixed> $context Provider-specific context (e.g. template_id).
     * @return IssueLog
     * @throws LocalizedException
     */
    public function schedule(int $orderId, bool $force = false, array $context = []): IssueLog
    {
        $storeId = $this->orderStoreIdProvider->getStoreIdByOrderId($orderId);

        if (!$force && !$this->config->isEnabled($storeId)) {
            throw new LocalizedException(__('EInvoice is disabled for this store.'));
        }

        if (!$force) {
            $successful = $this->issueLogRepository->findSuccessfulByOrderId($orderId);
            if ($successful !== null) {
                return $successful;
            }
        }

        $open = $this->issueLogRepository->findOpenByOrderId($orderId);
        if ($open !== null) {
            return $this->processLog($open);
        }

        if (!isset($context['ref_revision'])) {
            $context['ref_revision'] = $this->issueLogRepository->countByOrderId($orderId);
        }

        $request = $this->requestBuilderPool->get($storeId)->build($orderId, $context);
        $payload = $request->getPayload();

        /** @var IssueLog $log */
        $log = $this->issueLogFactory->create();
        $log->setData([
            'order_id' => $request->getOrderId(),
            'order_increment_id' => $request->getOrderIncrementId(),
            'store_id' => $storeId,
            'status' => IssueLog::STATUS_PENDING,
            'ref_id' => (string) ($payload['RefID'] ?? ''),
            'inv_series' => (string) ($payload['InvSeries'] ?? ''),
            'invoice_template_id' => (string) ($payload['InvoiceTemplateID'] ?? ''),
            'parent_log_id' => isset($context['parent_log_id']) ? (int) $context['parent_log_id'] : null,
            'reference_type' => (int) ($context['reference_type'] ?? IssueLog::REFERENCE_TYPE_ORIGINAL),
            'request_payload' => $this->json->serialize($payload),
        ]);
        $log->setLastAction(IssueLog::ACTION_ISSUE, $this->dateTime->gmtDate());
        $this->issueLogRepository->save($log);

        return $this->processLog($log);
    }

    /**
     * Issue immediately from Admin (forces retry even when a prior attempt failed).
     *
     * @param int $orderId
     * @param array<string, mixed> $context Provider-specific context (e.g. template_id).
     * @return IssueLog
     * @throws LocalizedException
     */
    public function issueNow(int $orderId, array $context = []): IssueLog
    {
        return $this->schedule($orderId, true, $context);
    }

    /**
     * Process a pending or failed log row by calling the configured provider.
     *
     * @param IssueLog $log
     * @return IssueLog
     * @throws LocalizedException
     */
    public function processLog(IssueLog $log): IssueLog
    {
        if ($log->getStatus() === IssueLog::STATUS_SUCCESS) {
            return $log;
        }

        $storeId = (int) $log->getData('store_id');
        if (!$this->config->isEnabled($storeId)) {
            throw new LocalizedException(__('EInvoice is disabled for this store.'));
        }

        $log->setStatus(IssueLog::STATUS_PROCESSING);
        $this->issueLogRepository->save($log);

        $invSeries = (string) $log->getData('inv_series');
        if ($invSeries === '') {
            $invSeries = $this->extractInvSeriesFromRequestPayload($log);
        }

        return $this->invSeriesPublishLock->execute($invSeries, function () use ($log, $storeId): IssueLog {
            $request = $this->buildRequestForProcessLog($log, $storeId);

            return $this->runIssuer($log, $request, $storeId, IssueLog::ACTION_ISSUE);
        });
    }

    /**
     * Run the configured issuer for a request and persist the outcome on the log.
     *
     * @param IssueLog $log
     * @param IssueRequestInterface $request
     * @param int $storeId
     * @param string $action Action code stored on the log for error attribution.
     * @return IssueLog
     * @throws LocalizedException
     */
    private function runIssuer(
        IssueLog $log,
        IssueRequestInterface $request,
        int $storeId,
        string $action = IssueLog::ACTION_ISSUE
    ): IssueLog
    {
        $log->setData('request_payload', $this->json->serialize($request->getPayload()));

        $result = $this->invoiceIssuerPool->get($storeId)->issue($request);
        $rawResponse = $result->getRawResponse();
        $log->setData('response_payload', $this->json->serialize($rawResponse));
        $meta = is_array($rawResponse['meta'] ?? null) ? $rawResponse['meta'] : [];
        if ($meta !== []) {
            $log->setData('ref_id', (string) ($meta['ref_id'] ?? ''));
            $log->setData('transaction_id', (string) ($meta['transaction_id'] ?? ''));
            $log->setData('inv_no', (string) ($meta['inv_no'] ?? ''));
            $log->setData('inv_series', (string) ($meta['inv_series'] ?? ''));
            $log->setData('invoice_template_id', (string) ($meta['invoice_template_id'] ?? ''));
            if (isset($meta['sign_type'])) {
                $log->setData('sign_type', (int) $meta['sign_type']);
            }
        }

        $payload = $request->getPayload();
        if (isset($payload['ReferenceType'])) {
            $log->setData('reference_type', (int) $payload['ReferenceType']);
        } elseif (!$log->getData('reference_type')) {
            $log->setData('reference_type', IssueLog::REFERENCE_TYPE_ORIGINAL);
        }

        if ($result->isSuccess()) {
            $log->setStatus(IssueLog::STATUS_SUCCESS);
            $log->setData('external_id', $result->getExternalId());
            $log->setData('issued_at', $this->dateTime->gmtDate());
            $this->recordActionOutcome($log, $action, true);
        } else {
            $log->setStatus(IssueLog::STATUS_FAILED);
            $message = $result->getMessage();
            if (!$this->publishRetryPolicy->mayRetry($message, isset($payload['RefID']))) {
                $message .= ' ' . __('Automatic retry is not allowed for this error; verify status or issue with a new RefID.');
            }
            $this->recordActionOutcome($log, $action, false, $message);
            $this->logger->error('EInvoice issue failed', [
                'order_id' => $log->getData('order_id'),
                'action' => $action,
                'message' => $message,
            ]);
        }

        $this->issueLogRepository->save($log);

        return $log;
    }

    /**
     * Return the most recent log row for an order.
     *
     * @param int $orderId
     * @return IssueLog|null
     */
    public function getLatestLogForOrder(int $orderId): ?IssueLog
    {
        return $this->issueLogRepository->findLatestByOrderId($orderId);
    }

    /**
     * Successful issue log with a MeInvoice transaction id (target of refresh/download/cancel).
     */
    public function getIssuedLogForOrder(int $orderId): ?IssueLog
    {
        $log = $this->issueLogRepository->findSuccessfulByOrderId($orderId);
        if ($log === null || (string) $log->getData('transaction_id') === '') {
            return null;
        }

        return $log;
    }

    /**
     * Log row for invoice details: issued invoice when present, otherwise the latest attempt.
     */
    public function getOrderTabLog(int $orderId): ?IssueLog
    {
        return $this->getIssuedLogForOrder($orderId) ?? $this->getLatestLogForOrder($orderId);
    }

    /**
     * Whether the module should react to a trigger for the given store.
     *
     * @param string $trigger
     * @param int|null $storeId
     * @return bool
     */
    public function shouldReactToTrigger(string $trigger, ?int $storeId = null): bool
    {
        if (!$this->config->isEnabled($storeId)) {
            return false;
        }

        return $this->config->getIssueTrigger($storeId) === $trigger;
    }

    /**
     * Refresh the MeInvoice status for an order's issued invoice and persist it.
     *
     * @param int $orderId
     * @return IssueLog
     * @throws LocalizedException
     */
    public function refreshStatus(int $orderId): IssueLog
    {
        $log = $this->requireSuccessfulLog($orderId);
        $storeId = (int) $log->getData('store_id');

        try {
            $status = $this->documentServicePool->get($storeId)
                ->getStatus((string) $log->getData('transaction_id'), $storeId);
            $this->mergeResponse($log, 'status_check', $status);
            $invoiceStatus = $this->extractInvoiceStatus($status);
            if ($invoiceStatus !== '') {
                $log->setData('invoice_status', $invoiceStatus);
            }
            $invNo = $this->extractInvNo($status);
            if ($invNo !== '') {
                $log->setData('inv_no', $invNo);
            }
            $this->recordActionOutcome($log, IssueLog::ACTION_REFRESH_STATUS, true);
        } catch (LocalizedException $exception) {
            $this->recordActionOutcome($log, IssueLog::ACTION_REFRESH_STATUS, false, $exception->getMessage());
            $this->issueLogRepository->save($log);
            throw $exception;
        }

        $this->issueLogRepository->save($log);

        return $log;
    }

    /**
     * Download an issued invoice file (pdf/xml).
     *
     * @param int $orderId
     * @param string $type
     * @return array{filename: string, mime: string, contents: string}
     * @throws LocalizedException
     */
    public function downloadInvoice(int $orderId, string $type): array
    {
        $log = $this->requireSuccessfulLog($orderId);
        $storeId = (int) $log->getData('store_id');

        return $this->documentServicePool->get($storeId)
            ->download((string) $log->getData('transaction_id'), $type, $storeId);
    }

    /**
     * Cancel an order's issued invoice on the provider.
     *
     * @param int $orderId
     * @param string $reason
     * @return IssueLog
     * @throws LocalizedException
     */
    public function cancelForOrder(int $orderId, string $reason): IssueLog
    {
        $log = $this->requireSuccessfulLog($orderId);
        $storeId = (int) $log->getData('store_id');
        $invSeries = (string) $log->getData('inv_series');
        if ($invSeries === '') {
            $invSeries = $this->extractInvSeriesFromRequestPayload($log);
        }
        if ($invSeries === '') {
            throw new LocalizedException(
                __('Cannot cancel: invoice series (InvSeries) is missing on the issue log.')
            );
        }

        $result = $this->documentServicePool->get($storeId)
            ->cancel((string) $log->getData('transaction_id'), $invSeries, $reason, $storeId);
        $this->mergeResponse($log, 'cancel', $result['raw'] ?? []);

        if ((bool) ($result['success'] ?? false)) {
            $log->setStatus(IssueLog::STATUS_CANCELLED);
            $log->setData('cancelled_at', $this->dateTime->gmtDate());
            $this->recordActionOutcome($log, IssueLog::ACTION_CANCEL, true);
        } else {
            $message = (string) ($result['message'] ?? '');
            $message = $message !== '' ? $message : (string) __('MeInvoice cancel failed.');
            $this->recordActionOutcome($log, IssueLog::ACTION_CANCEL, false, $message);
            $this->issueLogRepository->save($log);
            throw new LocalizedException(__('MeInvoice cancel failed: %1', $message));
        }

        $this->issueLogRepository->save($log);

        return $log;
    }

    /**
     * Send the issued invoice email to the customer via MeInvoice sendemail API.
     *
     * @param int $orderId
     * @param string|null $receiverEmail Overrides billing/order email when set.
     * @param string|null $receiverName Overrides billing name when set.
     * @param string|null $ccEmail
     * @param string|null $replyEmail
     * @return IssueLog
     * @throws LocalizedException
     */
    public function sendInvoiceEmailToCustomer(
        int $orderId,
        ?string $receiverEmail = null,
        ?string $receiverName = null,
        ?string $ccEmail = null,
        ?string $replyEmail = null
    ): IssueLog {
        $log = $this->requireSuccessfulLog($orderId);
        $storeId = (int) $log->getData('store_id');

        if ($receiverEmail === null || trim($receiverEmail) === '') {
            try {
                $order = $this->orderRepository->get($orderId);
            } catch (NoSuchEntityException $exception) {
                throw new LocalizedException(__('Order %1 not found.', $orderId), $exception);
            }
            $billing = $order->getBillingAddress();
            $receiverEmail = (string) ($billing?->getEmail() ?: $order->getCustomerEmail());
            if ($receiverName === null || trim($receiverName) === '') {
                $receiverName = trim((string) ($billing?->getFirstname() . ' ' . $billing?->getLastname()));
            }
        }

        $receiverEmail = trim((string) $receiverEmail);
        if ($receiverEmail === '') {
            throw new LocalizedException(__('Receiver email is required to send the invoice.'));
        }

        $receiverName = trim((string) ($receiverName ?? ''));
        if ($receiverName === '') {
            $receiverName = (string) __('Customer');
        }

        $result = $this->documentServicePool->get($storeId)->sendEmail(
            (string) $log->getData('transaction_id'),
            $receiverName,
            $receiverEmail,
            $ccEmail !== null && $ccEmail !== '' ? trim($ccEmail) : null,
            $replyEmail !== null && $replyEmail !== '' ? trim($replyEmail) : null,
            $storeId
        );

        $this->mergeResponse($log, 'send_email', $result['raw'] ?? []);

        if ((bool) ($result['success'] ?? false)) {
            $this->recordActionOutcome($log, IssueLog::ACTION_SEND_EMAIL, true);
        } else {
            $message = (string) ($result['message'] ?? '');
            $message = $message !== '' ? $message : (string) __('MeInvoice send email failed.');
            $this->recordActionOutcome($log, IssueLog::ACTION_SEND_EMAIL, false, $message);
            $this->issueLogRepository->save($log);
            throw new LocalizedException(__('MeInvoice send email failed: %1', $message));
        }

        $this->issueLogRepository->save($log);

        return $log;
    }

    /**
     * Cancel an order's invoice silently (used by order cancel observer).
     *
     * @param int $orderId
     * @param string $reason
     * @return void
     */
    public function cancelForOrderSafe(int $orderId, string $reason): void
    {
        try {
            if ($this->issueLogRepository->findSuccessfulByOrderId($orderId) === null) {
                return;
            }
            $this->cancelForOrder($orderId, $reason);
        } catch (LocalizedException $exception) {
            $this->logger->error('EInvoice auto cancel failed', [
                'order_id' => $orderId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Issue an adjustment invoice for a credit memo, referencing the original invoice.
     *
     * @param int $creditmemoId
     * @return IssueLog
     * @throws LocalizedException
     */
    public function issueAdjustment(int $creditmemoId): IssueLog
    {
        try {
            $creditmemo = $this->creditmemoRepository->get($creditmemoId);
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(__('Credit memo %1 not found.', $creditmemoId), $exception);
        }

        $orderId = (int) $creditmemo->getOrderId();
        $storeId = (int) $creditmemo->getStoreId();

        if (!$this->config->isEnabled($storeId)) {
            throw new LocalizedException(__('EInvoice is disabled for this store.'));
        }

        $origin = $this->issueLogRepository->findSuccessfulByOrderId($orderId);
        if ($origin === null || (string) $origin->getData('transaction_id') === '') {
            throw new LocalizedException(
                __('No issued invoice found for this order; cannot issue an adjustment.')
            );
        }

        $context = [
            'template_id' => (string) $origin->getData('invoice_template_id'),
            'origin' => $this->originInvoiceResolver->resolve($origin),
        ];

        $request = $this->adjustmentBuilderPool->get($storeId)->build($creditmemoId, $context);
        $payload = $request->getPayload();

        $refId = (string) ($payload['RefID'] ?? '');
        $existing = $this->issueLogRepository->findByRefId($refId);
        if ($existing !== null && $existing->getStatus() === IssueLog::STATUS_SUCCESS) {
            return $existing;
        }

        /** @var IssueLog $log */
        $log = $existing ?? $this->issueLogFactory->create();
        $log->setData([
            'order_id' => $orderId,
            'order_increment_id' => $request->getOrderIncrementId(),
            'store_id' => $storeId,
            'status' => IssueLog::STATUS_PENDING,
            'ref_id' => (string) ($payload['RefID'] ?? ''),
            'inv_series' => (string) ($payload['InvSeries'] ?? ''),
            'invoice_template_id' => (string) ($payload['InvoiceTemplateID'] ?? ''),
            'parent_log_id' => (int) $origin->getId(),
            'creditmemo_id' => $creditmemoId,
            'reference_type' => IssueLog::REFERENCE_TYPE_ADJUSTMENT,
            'request_payload' => $this->json->serialize($payload),
        ]);
        $log->setLastAction(IssueLog::ACTION_ADJUSTMENT, $this->dateTime->gmtDate());
        $this->issueLogRepository->save($log);

        $invSeries = (string) ($payload['InvSeries'] ?? '');

        return $this->invSeriesPublishLock->execute($invSeries, function () use ($log, $request, $storeId): IssueLog {
            return $this->runIssuer($log, $request, $storeId, IssueLog::ACTION_ADJUSTMENT);
        });
    }

    /**
     * Issue a replacement invoice (ReferenceType=1) for an order.
     *
     * @throws LocalizedException
     */
    public function issueReplace(int $orderId, array $context = []): IssueLog
    {
        $storeId = $this->orderStoreIdProvider->getStoreIdByOrderId($orderId);
        if (!$this->config->isEnabled($storeId)) {
            throw new LocalizedException(__('EInvoice is disabled for this store.'));
        }

        $origin = $this->issueLogRepository->findSuccessfulByOrderId($orderId);
        if ($origin === null) {
            throw new LocalizedException(__('No issued invoice found for replacement.'));
        }

        $context['ref_revision'] = $this->issueLogRepository->countByOrderId($orderId);
        $context['reference_type'] = IssueLog::REFERENCE_TYPE_REPLACE;
        $context['parent_log_id'] = (int) $origin->getId();
        $context['template_id'] = (string) $origin->getData('invoice_template_id');
        $context['origin'] = $this->originInvoiceResolver->resolve($origin);

        return $this->schedule($orderId, true, $context);
    }

    /**
     * Issue commercial discount invoice (ReferenceType=5).
     *
     * @param array<string, string|float> $discount
     * @throws LocalizedException
     */
    public function issueCommercialDiscount(int $orderId, array $discount): IssueLog
    {
        $storeId = $this->orderStoreIdProvider->getStoreIdByOrderId($orderId);
        if (!$this->config->isEnabled($storeId)) {
            throw new LocalizedException(__('EInvoice is disabled for this store.'));
        }

        if (!$this->config->isCommercialDiscountEnabled($storeId)) {
            throw new LocalizedException(__('Commercial discount (CKTM) is disabled for this store.'));
        }

        if ((float) ($discount['amount'] ?? 0) <= 0) {
            throw new LocalizedException(__('Discount amount must be greater than zero.'));
        }

        $origin = $this->issueLogRepository->findSuccessfulByOrderId($orderId);
        if ($origin === null || (string) $origin->getData('transaction_id') === '') {
            throw new LocalizedException(
                __('No issued invoice found for this order; cannot issue commercial discount.')
            );
        }

        $context = [
            'template_id' => (string) $origin->getData('invoice_template_id'),
            'origin' => $this->originInvoiceResolver->resolve($origin),
            'ref_revision' => $this->issueLogRepository->countByOrderId($orderId),
        ];

        $request = $this->commercialDiscountBuilderPool->get($storeId)->build($orderId, $discount, $context);
        $payload = $request->getPayload();

        /** @var IssueLog $log */
        $log = $this->issueLogFactory->create();
        $log->setData([
            'order_id' => $orderId,
            'order_increment_id' => $request->getOrderIncrementId(),
            'store_id' => $storeId,
            'status' => IssueLog::STATUS_PENDING,
            'ref_id' => (string) ($payload['RefID'] ?? ''),
            'inv_series' => (string) ($payload['InvSeries'] ?? ''),
            'invoice_template_id' => (string) ($payload['InvoiceTemplateID'] ?? ''),
            'parent_log_id' => (int) $origin->getId(),
            'reference_type' => IssueLog::REFERENCE_TYPE_COMMERCIAL_DISCOUNT,
            'request_payload' => $this->json->serialize($payload),
        ]);
        $log->setLastAction(IssueLog::ACTION_ISSUE, $this->dateTime->gmtDate());
        $this->issueLogRepository->save($log);

        $invSeries = (string) ($payload['InvSeries'] ?? '');

        return $this->invSeriesPublishLock->execute($invSeries, function () use ($log, $request, $storeId): IssueLog {
            return $this->runIssuer($log, $request, $storeId, IssueLog::ACTION_ISSUE);
        });
    }

    /**
     * Extract MeInvoice InvoiceStatus label from stored refresh response.
     */
    public function getInvoiceStatusLabel(int $orderId): ?string
    {
        $log = $this->getIssuedLogForOrder($orderId) ?? $this->getLatestLogForOrder($orderId);
        if ($log === null) {
            return null;
        }

        $stored = (string) $log->getData('invoice_status');
        if ($stored !== '') {
            return $stored;
        }

        $raw = (string) $log->getData('response_payload');
        if ($raw === '') {
            return null;
        }

        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (!is_array($decoded) || !isset($decoded['status_check']) || !is_array($decoded['status_check'])) {
            return null;
        }

        return $this->extractInvoiceStatus($decoded['status_check']) ?: null;
    }

    /**
     * @param array<string, mixed> $statusResponse
     */
    private function extractInvoiceStatus(array $statusResponse): string
    {
        $data = $statusResponse['data'] ?? null;
        if (is_string($data) && $data !== '') {
            try {
                $data = $this->json->unserialize($data);
            } catch (\InvalidArgumentException) {
                return '';
            }
        }
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        return is_array($data) ? (string) ($data['InvoiceStatus'] ?? $data['invoiceStatus'] ?? '') : '';
    }

    /**
     * @param array<string, mixed> $statusResponse
     */
    private function extractInvNo(array $statusResponse): string
    {
        $data = $statusResponse['data'] ?? null;
        if (is_string($data) && $data !== '') {
            try {
                $data = $this->json->unserialize($data);
            } catch (\InvalidArgumentException) {
                return '';
            }
        }
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        return is_array($data) ? (string) ($data['InvNo'] ?? $data['InvoiceNo'] ?? '') : '';
    }

    /**
     * Issue an adjustment invoice silently (used by credit memo observer).
     *
     * @param int $creditmemoId
     * @return void
     */
    public function issueAdjustmentSafe(int $creditmemoId): void
    {
        try {
            $this->issueAdjustment($creditmemoId);
        } catch (LocalizedException $exception) {
            $this->logger->info('EInvoice adjustment skipped/failed', [
                'creditmemo_id' => $creditmemoId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Return the successful issue log for an order or fail.
     *
     * @param int $orderId
     * @return IssueLog
     * @throws LocalizedException
     */
    private function requireSuccessfulLog(int $orderId): IssueLog
    {
        $log = $this->issueLogRepository->findSuccessfulByOrderId($orderId);
        if ($log === null) {
            throw new LocalizedException(__('No issued invoice was found for this order.'));
        }
        if ((string) $log->getData('transaction_id') === '') {
            throw new LocalizedException(__('The issued invoice has no MeInvoice transaction id.'));
        }

        return $log;
    }

    /**
     * Merge a sub-result into the stored response payload of a log.
     *
     * @param IssueLog $log
     * @param string $key
     * @param array<string, mixed> $data
     * @return void
     */
    private function mergeResponse(IssueLog $log, string $key, array $data): void
    {
        $existing = [];
        $raw = (string) $log->getData('response_payload');
        if ($raw !== '') {
            try {
                $decoded = $this->json->unserialize($raw);
                $existing = is_array($decoded) ? $decoded : [];
            } catch (\InvalidArgumentException $exception) {
                $existing = [];
            }
        }
        $existing[$key] = $data;
        $log->setData('response_payload', $this->json->serialize($existing));
    }

    /**
     * Rebuild the issue request from persisted log metadata (replace/adjustment context).
     */
    private function buildRequestForProcessLog(IssueLog $log, int $storeId): IssueRequestInterface
    {
        $request = $this->requestBuilderPool->get($storeId)->build(
            (int) $log->getData('order_id'),
            $this->buildProcessContextFromLog($log)
        );

        $storedRefId = (string) $log->getData('ref_id');
        if ($storedRefId !== '') {
            $payload = $request->getPayload();
            $payload['RefID'] = $storedRefId;
            $request->setPayload($payload);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProcessContextFromLog(IssueLog $log): array
    {
        $orderId = (int) $log->getData('order_id');
        $referenceType = (int) ($log->getData('reference_type') ?? IssueLog::REFERENCE_TYPE_ORIGINAL);
        $context = [
            'template_id' => (string) $log->getData('invoice_template_id'),
            'reference_type' => $referenceType,
            'ref_revision' => max(0, $this->issueLogRepository->countByOrderId($orderId) - 1),
        ];

        if ($referenceType !== IssueLog::REFERENCE_TYPE_REPLACE) {
            return $context;
        }

        $parentLogId = (int) $log->getData('parent_log_id');
        if ($parentLogId <= 0) {
            return $context;
        }

        $parent = $this->loadIssueLogById($parentLogId);
        if ($parent !== null) {
            $context['origin'] = $this->originInvoiceResolver->resolve($parent);
            $context['parent_log_id'] = $parentLogId;
        }

        return $context;
    }

    private function loadIssueLogById(int $logId): ?IssueLog
    {
        /** @var IssueLog $log */
        $log = $this->issueLogFactory->create();
        $log->load($logId);

        return $log->getId() ? $log : null;
    }

    /**
     * Read InvSeries from stored issue request JSON when the dedicated column is empty.
     */
    private function extractInvSeriesFromRequestPayload(IssueLog $log): string
    {
        $raw = (string) $log->getData('request_payload');
        if ($raw === '') {
            return '';
        }

        try {
            $payload = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return '';
        }

        return is_array($payload) ? (string) ($payload['InvSeries'] ?? '') : '';
    }

    /**
     * Record which action ran and store or clear its error separately from other actions.
     */
    private function recordActionOutcome(
        IssueLog $log,
        string $action,
        bool $success,
        ?string $errorMessage = null
    ): void {
        $log->setLastAction($action, $this->dateTime->gmtDate());
        if ($success) {
            $log->setActionError($action, null);
            return;
        }

        $log->setActionError(
            $action,
            $errorMessage !== null && $errorMessage !== ''
                ? $errorMessage
                : (string) __('Operation failed.')
        );
    }
}
