<?php
/**
 * MoMo refund command (/v2/gateway/api/refund) with durable identity +
 * uncertainty-safe classification (MOMO-02).
 *
 * Flow: openIdentity (guards + pending row, BEFORE any provider call) →
 * build (minted identity) → HTTP → classify (echo + resultCode contract,
 * never HTTP-2xx-only) → record outcome (independent connection, survives
 * the CreditmemoService rollback) → SUCCESS only lets the native creditmemo
 * accounting proceed; FAILED/UNKNOWN throw so Magento rolls the creditmemo
 * back and no accounting is falsely finalized.
 *
 * The response carries no signature (verified provider contract); SUCCESS
 * is trusted only when the echoes of the EXACT request match and
 * resultCode == 0.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Service\RefundClassification;
use Secomm\MoMo\Service\RefundRequestManager;
use Secomm\MoMo\Service\RefundResultClassifier;

class RefundCommand implements CommandInterface
{
    /**
     * @var RefundRequestManager
     */
    private RefundRequestManager $manager;

    /**
     * @var RefundResultClassifier
     */
    private RefundResultClassifier $classifier;

    /**
     * @var BuilderInterface
     */
    private BuilderInterface $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private TransferFactoryInterface $transferFactory;

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @var HandlerInterface
     */
    private HandlerInterface $handler;

    /**
     * @var ArrayResultFactory
     */
    private ArrayResultFactory $resultFactory;

    /**
     * RefundCommand constructor.
     *
     * @param RefundRequestManager $manager
     * @param RefundResultClassifier $classifier
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param HandlerInterface $handler
     * @param ArrayResultFactory $resultFactory
     */
    public function __construct(
        RefundRequestManager $manager,
        RefundResultClassifier $classifier,
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        HandlerInterface $handler,
        ArrayResultFactory $resultFactory
    ) {
        $this->manager = $manager;
        $this->classifier = $classifier;
        $this->requestBuilder = $requestBuilder;
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->handler = $handler;
        $this->resultFactory = $resultFactory;
    }

    /**
     * Execute the refund with durable identity and uncertainty safety.
     *
     * @param array $commandSubject
     * @return ArrayResult|ResultInterface|null
     * @throws LocalizedException When the refund is blocked, refused, or its
     *         outcome is unconfirmed — the native creditmemo rolls back.
     * @throws ClientException
     * @throws ConverterException Only when classification is impossible
     *         without the transport detail (converted to UNKNOWN first).
     */
    public function execute(array $commandSubject): ResultInterface|ArrayResult|null
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $amount = (int)round((float)SubjectReader::readAmount($commandSubject));

        // Guards + pending row BEFORE any provider instruction is sent:
        // a blocked or duplicated refund never reaches MoMo twice.
        $refund = $this->manager->openIdentity($paymentDO, $amount);

        $request = $this->requestBuilder->build(
            array_merge($commandSubject, ['momo_refund_row' => $refund])
        );

        try {
            $transfer = $this->transferFactory->create($request);
            $response = $this->client->placeRequest($transfer);
        } catch (ClientException | ConverterException $e) {
            // Timeout / transport / unreadable body: outcome unknown —
            // never FAILED, never a silent retry (AC5).
            $this->manager->recordOutcome(
                $refund,
                RefundClassification::transportError(),
                $this->sanitizeTransportError($e->getMessage())
            );

            throw new LocalizedException(
                __(
                    'The MoMo refund outcome could not be confirmed (request %1).'
                    . ' No retry was attempted; resolve it with'
                    . ' bin/magento momo:refund:resolve %1 before refunding again.',
                    $refund->getRequestId()
                )
            );
        }

        $classification = $this->classifier->classify(
            [
                'requestId' => (string)$request['requestId'],
                'refund_order_id' => (string)$request['orderId'],
                'amount' => (int)$request['amount'],
                'partner_code' => (string)$request['partnerCode'],
            ],
            $response
        );
        $this->manager->recordOutcome($refund, $classification);

        if (!$classification->isSuccess()) {
            if ($classification->status === RefundRequestInterface::STATUS_FAILED) {
                throw new LocalizedException(
                    __(
                        'MoMo refused the refund (code %1): %2 Request reference: %3.',
                        $classification->responseCode ?? 'n/a',
                        $classification->responseMessage ?: 'no provider message',
                        $refund->getRequestId()
                    )
                );
            }

            throw new LocalizedException(
                __(
                    'The MoMo refund outcome could not be confirmed (request %1).'
                    . ' No retry was attempted; resolve it with'
                    . ' bin/magento momo:refund:resolve %1 before refunding again.',
                    $refund->getRequestId()
                )
            );
        }

        // Provider-confirmed SUCCESS only now: the native creditmemo
        // accounting continues (invoice/payment/order refunded totals).
        $this->handler->handle($commandSubject, $response);

        return $this->resultFactory->create(['array' => $response]);
    }

    /**
     * Truncate a transport error to a safe operator-facing detail (no
     * payload material is ever embedded by the transport layer, but the
     * message is still bounded before persistence).
     *
     * @param string|null $message
     * @return string|null
     */
    private function sanitizeTransportError(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        return mb_substr($message, 0, 255);
    }
}
