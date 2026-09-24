<?php
/**
 * Backfills the creditmemo id onto the MoMo refund row after a successful
 * online refund.
 *
 * Magento_Sales CreditmemoManagementInterface::refund() saves the creditmemo
 * inside its sales transaction; the gateway command runs BEFORE the id
 * exists. This plugin runs AFTER refund() returns (i.e. after commit): it
 * reads the refund requestId the RefundHandler recorded on the payment and
 * writes creditmemo_id onto the MoMo-owned refund row via the independent
 * connection. Read-only on sales data; no sales mutation.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Plugin\Sales;

use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Secomm\MoMo\Api\RefundRequestRepositoryInterface;
use Secomm\MoMo\Gateway\Response\RefundHandler;

/**
 * Creditmemo linkage backfill (read-only on sales, write to the MoMo row only).
 */
class CreditmemoService
{
    /** MoMo payment method code. */
    public const METHOD_CODE = 'momo_payment';

    /**
     * Refund request repository (independent connection).
     *
     * @var RefundRequestRepositoryInterface
     */
    private $repository;

    /**
     * CreditmemoService constructor.
     *
     * @param RefundRequestRepositoryInterface $repository
     */
    public function __construct(RefundRequestRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Backfill creditmemo_id after a successful ONLINE refund (the gateway
     * path). Offline refunds and non-MoMo payments are no-ops.
     *
     * @param CreditmemoManagementInterface $subject
     * @param CreditmemoInterface $result
     * @param CreditmemoInterface $creditmemo
     * @param bool $offlineRequested False means ONLINE refund (gateway path).
     * @return CreditmemoInterface
     */
    public function afterRefund(
        CreditmemoManagementInterface $subject,
        CreditmemoInterface $result,
        CreditmemoInterface $creditmemo,
        $offlineRequested = false
    ) {
        $creditmemoId = (int)$creditmemo->getEntityId();
        $payment = $creditmemo->getOrder() ? $creditmemo->getOrder()->getPayment() : null;
        $requestId = '';
        if ($payment !== null) {
            $requestId = (string)($payment->getAdditionalInformation(RefundHandler::KEY_REFUND_REQUEST_ID) ?? '');
        }

        if ($offlineRequested
            || $creditmemoId <= 0
            || $requestId === ''
            || $payment === null
            || $payment->getMethod() !== self::METHOD_CODE
        ) {
            return $result;
        }

        $row = $this->repository->getByRequestId($requestId);
        if ($row === null || $row->getEntityId() === null) {
            return $result;
        }

        $this->repository->markCreditmemo($row->getEntityId(), $creditmemoId);

        return $result;
    }
}
