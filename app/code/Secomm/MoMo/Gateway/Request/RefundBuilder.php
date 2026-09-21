<?php
/**
 * Builds the MoMo refund (/v2/gateway/api/refund) request payload.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\Exception\LocalizedException;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Model\Config;
use Secomm\MoMo\Model\OrderRefBuilder;

/**
 * Builds the MoMo refund payload.
 *
 * Refund identity (MOMO-02): the minted refund_order_id + request_id come
 * from the durable refund row (carried in the command subject), so the
 * provider payload can never disagree with the persisted reconciliation
 * evidence. The refund orderId is ALWAYS distinct from the original
 * purchase orderId (MoMo contract requirement) and the requestId is the
 * provider idempotency key — minted once, stored, never regenerated.
 *
 * The ORIGINAL payment identity still uses the attempt order_ref (`momo_order_ref`,
 * bound onto the order payment by the OrderFinalizer) plus the MoMo
 * transId (`momo_trans_id`).
 *
 * Legacy compat: orders placed under the previous order-first flow have no
 * momo_order_ref; their MoMo create-time orderId WAS the increment id, so
 * the builder falls back to it. The transId falls back to the legacy
 * additional information keys ('transId' / 'momo_trans_id' written by the
 * deleted TransactionHandler). When neither identity is present the refund
 * cannot be built — a clear LocalizedException instead of a garbage
 * request.
 */
class RefundBuilder implements BuilderInterface
{
    /** Payment additional information: MoMo order_ref (create-time orderId). */
    public const KEY_ORDER_REF = 'momo_order_ref';

    /** Payment additional information: MoMo transId. */
    public const KEY_TRANS_ID = 'momo_trans_id';

    /** Legacy additional information key from the deleted TransactionHandler. */
    public const KEY_TRANS_ID_LEGACY = 'transId';

    /**
     * RefundBuilder constructor.
     *
     * @param Config $config
     * @param Signature $signature
     * @param OrderRefBuilder $orderRefBuilder
     */
    public function __construct(
        private readonly Config $config,
        private readonly Signature $signature,
        private readonly OrderRefBuilder $orderRefBuilder
    ) {
    }

    /**
     * Build the MoMo refund payload.
     *
     * @param array $buildSubject
     * @return array<string, scalar>
     * @throws LocalizedException When neither the MoMo refund identity nor a
     *         legacy identity can be resolved from the payment.
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();

        $amount = (int)round((float)SubjectReader::readAmount($buildSubject));
        $orderRef = (string)($payment->getAdditionalInformation(self::KEY_ORDER_REF)
            ?? $order->getOrderIncrementId());
        $transId = (string)($payment->getAdditionalInformation(self::KEY_TRANS_ID)
            ?? $payment->getAdditionalInformation(self::KEY_TRANS_ID_LEGACY)
            ?? '');
        if ($transId === '') {
            throw new LocalizedException(
                __('MoMo transaction reference is missing; the refund cannot be sent to MoMo.')
            );
        }

        $row = $buildSubject['momo_refund_row'] ?? null;
        if ($row instanceof RefundRequestInterface) {
            $requestId = $row->getRequestId();
            $orderId = $row->getRefundOrderId();
        } else {
            // Fallback (row unavailable): still mint a distinct refund
            // orderId/requestId so the purchase identity is never reused.
            $orderId = $this->orderRefBuilder->buildRefundOrderId($orderRef);
            $requestId = $this->orderRefBuilder->buildRefundRequestId($orderRef);
        }
        $description = (string)__('Refund for order #%1', $order->getOrderIncrementId());

        // rawSignature MUST follow MoMo's exact refund field order.
        $rawParams = [
            'accessKey' => $this->config->getAccessKey(),
            'amount' => $amount,
            'description' => $description,
            'orderId' => $orderId,
            'partnerCode' => $this->config->getPartnerCode(),
            'requestId' => $requestId,
            'transId' => $transId,
        ];

        return [
            'partnerCode' => $this->config->getPartnerCode(),
            'accessKey' => $this->config->getAccessKey(),
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'transId' => $transId,
            'description' => $description,
            'signature' => $this->signature->sign($rawParams, $this->config->getSecretKey()),
            'lang' => 'vi',
        ];
    }
}
