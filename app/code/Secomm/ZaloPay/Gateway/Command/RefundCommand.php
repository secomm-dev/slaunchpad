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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Exception\RefundProtocolException;
use Secomm\ZaloPay\Exception\RefundTransportException;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Helper\RefundProcessor;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Service\RefundOutcomeMarker;

/**
 * Provider-only ZaloPay refund command: ONE v2/refund call plus the
 * immediate v2/query_refund when ZaloPay answers PROCESSING.
 *
 * TASK-CG6BM7 corrective round - invariant: ZaloPay PROCESSING != Magento
 * refund completed. This command:
 *  - NEVER persists pending refund state and NEVER touches creditmemo/order
 *    accounting (that belongs to CreditmemoRefundPlugin + PendingRefundManager);
 *  - reports the outcome (SUCCESS/PROCESSING) via RefundOutcome to its
 *    orchestrating caller (Magento core ignores command return values);
 *  - throws LocalizedException (provider refusal, safe provider-map message)
 *    or RefundTransportException (timeout/network: outcome UNKNOWN - the
 *    caller tracks the refund durably from the carried outcome) on failure;
 *  - throws RefundProtocolException when the response violates the provider
 *    protocol (return_code missing / non-numeric / outside {1,2,3}) - the
 *    outcome is NOT confirmable and explicitly NOT a confirmed refusal
 *    (round 7 F30);
 *  - SKIPS the provider call exactly once when RefundOutcomeMarker carries
 *    a consumed one-shot authorization for THIS credit memo id - the "no
 *    second provider refund" guarantee while Magento core accounting (which
 *    routes through this command via Payment::refund) finalizes a locally
 *    tracked refund (round 7 F28: exact-refund one-shot, fail-closed).
 *
 * Only a LocalizedException is ever thrown to Magento (Payment::refund
 * catches exactly that type); raw provider/transport text never reaches the
 * UI - admin messages come from the provider status map (RefundProcessor).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RefundCommand implements CommandInterface
{
    /**
     * Admin-facing message prefix.
     */
    public const PREFIX_ZALO_PAY_MESSAGE = 'Zalopay: ';

    /**
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param Logger $logger
     * @param RefundQueryCommand $refundQueryCommand
     * @param Rate $rate
     * @param Json $serializer
     * @param RefundOutcomeMarker $outcomeMarker
     * @param HandlerInterface|null $handler
     * @param ValidatorInterface|null $validator
     */
    public function __construct(
        private readonly BuilderInterface           $requestBuilder,
        private readonly TransferFactoryInterface   $transferFactory,
        private readonly ClientInterface            $client,
        private readonly Logger                     $logger,
        private readonly RefundQueryCommand         $refundQueryCommand,
        private readonly Rate                       $rate,
        private readonly Json                       $serializer,
        private readonly RefundOutcomeMarker        $outcomeMarker,
        private ?HandlerInterface                   $handler = null,
        private ?ValidatorInterface                 $validator = null
    ) {
    }

    /**
     * Run ONE provider refund interaction and report the outcome.
     *
     * Round 3: execute() = prepare() + executePrepared(). The public
     * contract for the core Payment::refund path is unchanged (returns
     * null on the marker skip path, LocalizedException on refusal).
     *
     * @param array $commandSubject
     * @return RefundOutcome|null Null on the skip path (one-shot provider-
     *         skip authorization consumed for this credit memo).
     * @throws ClientException
     * @throws ConverterException
     * @throws LocalizedException Provider refusal (safe mapped message).
     * @throws RefundProtocolException Protocol anomaly (round 7 F30).
     * @throws RefundTransportException Transport failure: outcome UNKNOWN,
     *         the exception carries the tracking outcome.
     */
    public function execute(array $commandSubject): ?RefundOutcome
    {
        $request = $this->prepare($commandSubject);
        if ($request === null) {
            return null;
        }

        return $this->executePrepared($request, $commandSubject);
    }

    /**
     * Prepare the provider refund request identity WITHOUT any provider
     * I/O (TASK-CG6BM7 corrective round 3): the request body, the stable
     * m_refund_id and the v2/query_refund reconciliation payload are built
     * here so the orchestrating plugin can persist a durable claim BEFORE
     * executePrepared touches the network. Identity stability: the exact
     * request body built here is reused by executePrepared - never rebuilt.
     *
     * Round 7 F28: the provider-skip is a ONE-SHOT authorization keyed by
     * THIS credit memo's entity id (a credit memo exists for exactly one
     * refund attempt). consume() removes the pin - core accounting (via
     * Payment::refund) reaches this command exactly once per finalize, the
     * provider is skipped exactly once, and a second refund on the same
     * order (a different credit memo) can never inherit the skip.
     *
     * @param array $commandSubject
     * @return RefundRequest|null Null on the skip path (a one-shot
     *         provider-skip authorization was consumed for this credit
     *         memo - core is finalizing a tracked refund).
     * @throws LocalizedException Missing credit memo on the payment.
     */
    public function prepare(array $commandSubject): ?RefundRequest
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $payment = $paymentDO->getPayment();
        $creditMemo = $payment->getCreditmemo();
        if ($creditMemo === null) {
            throw new LocalizedException(__('Zalopay: The credit memo for this refund cannot be found.'));
        }

        if ($this->outcomeMarker->consume((int)$creditMemo->getEntityId())) {
            // One-shot authorization consumed: core accounting is finalizing
            // a locally tracked refund for THIS credit memo - the provider
            // outcome is already known. NEVER re-ask the provider (no second
            // provider refund for any money).
            return null;
        }

        $requestData = $this->buildRequestData($commandSubject);
        $mRefundId = (string)$requestData[RefundInterface::M_REFUND_ID];
        $this->logger->info('ZaloPay refund request prepared.', ['m_refund_id' => $mRefundId]);

        // The durable reconciliation payload is built BEFORE the provider
        // call: if the transport fails after the request reached ZaloPay the
        // refund can still be tracked (and reconciled - never re-requested)
        // from this payload, which is safe to re-sign and re-query.
        try {
            $queryPayload = $this->buildQueryPayload($commandSubject, $mRefundId);
        } catch (\Exception $exception) {
            $queryPayload = null;
            $this->logger->error(
                'ZaloPay refund query payload build failed: ' . $exception->getMessage(),
                ['m_refund_id' => $mRefundId]
            );
        }
        $tracking = new RefundOutcome(
            RefundOutcome::STATUS_PROCESSING,
            $mRefundId,
            $this->readVndAmount($commandSubject),
            $queryPayload
        );

        return new RefundRequest($requestData, $mRefundId, $queryPayload, $tracking);
    }

    /**
     * Perform the provider I/O for a PREPARED request (stable identity).
     * NEVER call this without a durable claim for the same m_refund_id -
     * the claim is what makes recovery query the SAME identity instead of
     * issuing a fresh /refund after a lost response.
     *
     * @param RefundRequest $request Prepared request identity.
     * @param array $commandSubject
     * @return RefundOutcome
     * @throws ClientException
     * @throws ConverterException
     * @throws LocalizedException Provider refusal (return_code=2 only - the
     *         ONE confirmed-refusal path, safe mapped message).
     * @throws RefundProtocolException Protocol anomaly (round 7 F30):
     *         return_code missing / non-numeric / outside {1,2,3} - the
     *         provider state is NOT confirmable, which is NOT a refusal.
     * @throws RefundTransportException Transport failure: outcome UNKNOWN,
     *         the exception carries the tracking outcome.
     */
    public function executePrepared(RefundRequest $request, array $commandSubject): RefundOutcome
    {
        $requestData = $request->getRequestData();
        $mRefundId = $request->getMRefundId();
        $tracking = $request->getTracking();

        try {
            $transferO = $this->transferFactory->create($requestData);
            $response = $this->client->placeRequest($transferO);
        } catch (\Exception $exception) {
            // Transport failure: the provider outcome is UNKNOWN (the request
            // may or may not have arrived). The caller (CreditmemoRefundPlugin)
            // tracks this refund durably from the carried outcome and the cron
            // reconciles it by m_refund_id - it must NEVER be silently retried
            // with a fresh request id (double-refund guard).
            $this->logger->error(
                'ZaloPay refund transport failure: ' . $exception->getMessage(),
                ['m_refund_id' => $mRefundId]
            );

            throw new RefundTransportException(
                __(self::PREFIX_ZALO_PAY_MESSAGE . 'Refund failed. Please try again later.'),
                $exception,
                $tracking
            );
        }

        // Round 7 F30 - typed provider classification. Official v2/refund
        // return_code: 1=SUCCESS, 2=FAIL, 3=PROCESSING. Anything else is a
        // protocol anomaly: the provider state is NOT confirmable, so it is
        // NEVER folded into the confirmed-refusal path (that is return_code
        // = 2 only) - the caller lands durable UNKNOWN and reconciles by
        // m_refund_id instead of ever re-asking /refund.
        $statusCode = $this->readReturnCode($response);
        if ($statusCode === null) {
            $this->logger->error(
                'ZaloPay refund protocol anomaly: missing or non-numeric return_code.',
                ['m_refund_id' => $mRefundId]
            );

            throw new RefundProtocolException($this->notConfirmableMessage());
        }

        if ($statusCode === AbstractResponseValidator::REFUND_PROCESSING) {
            return $this->resolveProcessing($commandSubject, $response, $tracking);
        }

        if ($statusCode !== AbstractResponseValidator::RETURN_CODE_ACCEPT
            && $statusCode !== AbstractResponseValidator::REFUND_FAIL
        ) {
            // Numeric but outside the documented set (e.g. 999): anomaly.
            $this->logger->error(
                sprintf('ZaloPay refund protocol anomaly: unexpected provider return_code=%d.', $statusCode),
                ['m_refund_id' => $mRefundId]
            );

            throw new RefundProtocolException($this->notConfirmableMessage());
        }

        if ($statusCode === AbstractResponseValidator::REFUND_FAIL) {
            $this->throwProviderFailure($statusCode, $mRefundId);
        }

        $this->handler?->handle($commandSubject, $response);

        return new RefundOutcome(
            RefundOutcome::STATUS_SUCCESS,
            $mRefundId,
            $tracking->getVndAmount()
        );
    }

    /**
     * Resolve the PROCESSING window with ONE immediate v2/query_refund. A
     * query SUCCESS confirms the refund in-line; anything else (still
     * PROCESSING, unknown code, query transport failure) stays PROCESSING -
     * the refund request IS accepted by the provider, so it is tracked
     * durably (never re-requested, never lost).
     *
     * @param array $commandSubject
     * @param array $refundResponse Original v2/refund response (carries refund_id).
     * @param RefundOutcome $tracking Pre-built tracking outcome.
     * @return RefundOutcome
     * @throws LocalizedException Provider refused (confirmed by the query).
     */
    private function resolveProcessing(
        array        $commandSubject,
        array        $refundResponse,
        RefundOutcome $tracking
    ): RefundOutcome {
        $queryPayload = $tracking->getQueryPayload();

        if ($queryPayload === null) {
            // No reconciliation payload: the refund IS accepted by the
            // provider (rc=3), so it is still tracked durably; the cron
            // terminal-reconciles this row with evidence for manual follow-up.
            return $tracking;
        }

        try {
            $queryResponse = $this->refundQueryCommand->getRefundQuery($this->decodePayload($queryPayload));
        } catch (RefundTransportException $exception) {
            // Immediate query transport failure: the refund IS accepted and
            // running at the provider - stay PROCESSING (tracked durably).
            $this->logger->error(
                'ZaloPay refund immediate query transport failure: ' . $exception->getMessage(),
                ['m_refund_id' => $tracking->getMRefundId()]
            );

            return $tracking;
        }

        $queryCode = $this->readReturnCode($queryResponse);
        if ($queryCode === AbstractResponseValidator::RETURN_CODE_ACCEPT) {
            $this->handler?->handle($commandSubject, $refundResponse);

            return new RefundOutcome(
                RefundOutcome::STATUS_SUCCESS,
                $tracking->getMRefundId(),
                $tracking->getVndAmount()
            );
        }

        if ($queryCode === AbstractResponseValidator::REFUND_FAIL) {
            // Explicit provider refusal confirmed by the immediate query.
            $this->throwProviderFailure($queryCode, $tracking->getMRefundId());
        }

        return $tracking;
    }

    /**
     * Build the stored v2/query_refund payload (re-signed on every cron run):
     * app_id + m_refund_id + fresh timestamp + MAC - the durable evidence
     * that lets the cron reconcile the refund without ever re-requesting it.
     *
     * @param array $commandSubject
     * @param string $mRefundId
     * @return string Serialized JSON payload.
     */
    private function buildQueryPayload(array $commandSubject, string $mRefundId): string
    {
        $querySubject = $this->refundQueryCommand->setZaloRefundId($mRefundId)->buildRequestData($commandSubject);

        return $this->serializer->serialize($querySubject);
    }

    /**
     * @param string $payload
     * @return array
     */
    private function decodePayload(string $payload): array
    {
        $decoded = $this->serializer->unserialize($payload);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * VND refund amount exactly as the request builder computes it.
     *
     * @param array $commandSubject
     * @return int
     * @throws LocalizedException
     */
    private function readVndAmount(array $commandSubject): int
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $amount = round((float)SubjectReader::readAmount($commandSubject), 2);

        return (int)$this->rate->getVndAmount($paymentDO->getOrder(), $amount);
    }

    /**
     * Safe read of the provider return_code (missing/non-numeric = anomaly).
     *
     * @param array $response
     * @return int|null
     */
    private function readReturnCode(array $response): ?int
    {
        $code = $response[AbstractResponseValidator::RETURN_CODE] ?? null;

        return is_numeric($code) ? (int)$code : null;
    }

    /**
     * The safe, provider-agnostic message for a refund whose provider
     * state is not confirmable (protocol anomaly - round 7 F30). Raw
     * provider text never reaches the user.
     *
     * @return \Magento\Framework\Phrase
     */
    private function notConfirmableMessage(): \Magento\Framework\Phrase
    {
        return __(
            'Zalopay: Refund status could not be confirmed. The refund is'
            . ' tracked and will be reconciled automatically.'
        );
    }

    /**
     * Explicit provider-side refusal: map the CURRENT response's
     * sub_return_code through the safe status map (never raw provider or
     * transport text) and throw - the exception type Payment::refund catches.
     *
     * @param int|null $statusCode
     * @param string $mRefundId
     * @return void
     * @throws LocalizedException
     */
    private function throwProviderFailure(?int $statusCode, string $mRefundId): void
    {
        $this->logger->error(
            sprintf(
                'ZaloPay refund refused by provider: return_code=%s',
                var_export($statusCode, true)
            ),
            ['m_refund_id' => $mRefundId]
        );

        $statusMessage = $statusCode === AbstractResponseValidator::REFUND_FAIL
            ? RefundProcessor::processRefundStatus(AbstractResponseValidator::REFUND_FAIL)
            : (string)__('Refund failed. Please try again later.');

        throw new LocalizedException(
            __(self::PREFIX_ZALO_PAY_MESSAGE . $statusMessage)
        );
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
