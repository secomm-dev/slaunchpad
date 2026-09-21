<?php
/**
 * Records the MoMo refund result on the payment.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class RefundHandler implements HandlerInterface
{
    /** Payment additional information: MoMo transId of the refund itself. */
    public const KEY_REFUND_TRANS_ID = 'momo_refund_trans_id';

    /** Payment additional information: requestId of the refund row (post-commit backfill key). */
    public const KEY_REFUND_REQUEST_ID = 'momo_refund_request_id';

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        $payment->setAdditionalInformation(
            self::KEY_REFUND_TRANS_ID,
            $response['transId'] ?? $response['requestId'] ?? ''
        );
        // Written inside the sales transaction (rolled back with the
        // creditmemo on failure). On success it lets the post-commit
        // CreditmemoService plugin find the refund row to backfill the
        // creditmemo id onto.
        $payment->setAdditionalInformation(
            self::KEY_REFUND_REQUEST_ID,
            $response['requestId'] ?? ''
        );
    }
}
