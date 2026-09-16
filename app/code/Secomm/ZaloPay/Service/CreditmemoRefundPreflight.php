<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * TASK-CG6BM7 corrective round 2 - BLOCKER: never call ZaloPay before
 * Magento refund validation. The provider refund runs BEFORE the core flow
 * (CreditmemoRefundPlugin orchestrates the lifecycle), so the Magento-side
 * refund validation must run BEFORE the provider call - otherwise an invalid
 * Magento refund (over-refund, already-processed credit memo, missing order)
 * could take real money out at ZaloPay and only then be rejected locally.
 *
 * MIRROR of Magento\Sales\Model\Service\CreditmemoService::validateForRefund()
 * - the core method is protected (no public validation API exists for this
 * contract), verified against Magento 2.4.8-p5 source:
 * vendor/magento/module-sales/Model/Service/CreditmemoService.php lines
 * 189-219. Checks are mirrored 1:1 in the same order with the same message
 * contracts:
 *
 *   1. an EXISTING credit memo must be STATE_OPEN ("We cannot register an
 *      existing credit memo.");
 *   2. the credit memo must reference an order and that order must resolve
 *      ("We found an invalid order to refund." - NoSuchEntityException);
 *   3. rounded(base_total_refunded + base_grand_total) must not exceed
 *      rounded(base_total_paid) ("The most money available to refund is %1.").
 *
 * UPGRADE COUPLING (explicit): if Magento changes validateForRefund's checks
 * or messages in a future release, THIS mirror must be re-diffed against the
 * new source. The parity matrix in
 * Test/Unit/Service/CreditmemoRefundPreflightTest.php pins every mirrored
 * check so a core change surfaces as a review reminder, not silent drift.
 *
 * Supplementary (beyond the core mirror, required by the corrective
 * directive): an ONLINE gateway refund must carry a positive amount - core
 * only validates the accumulated refund balance, a zero/negative credit memo
 * amount would reach the provider.
 */
class CreditmemoRefundPreflight
{
    /**
     * @param PriceCurrencyInterface $priceCurrency Same rounding as core validation.
     */
    public function __construct(
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * Magento refund validation preflight (runs BEFORE the provider call).
     *
     * @param CreditmemoInterface $creditmemo
     * @return void
     * @throws LocalizedException Over-refund, already-processed credit memo,
     *         non-positive online refund amount.
     * @throws NoSuchEntityException Missing/unresolvable order reference.
     */
    public function validateRefundable(CreditmemoInterface $creditmemo): void
    {
        // Core mirror check 1 (CreditmemoService.php:192-197): an existing
        // credit memo must still be open.
        if ($creditmemo->getId() && $creditmemo->getState() != Creditmemo::STATE_OPEN) {
            throw new LocalizedException(__('We cannot register an existing credit memo.'));
        }

        // Core mirror check 2 (CreditmemoService.php:199-203): the credit
        // memo must reference a resolvable order.
        if (!$creditmemo->getOrderId() || $creditmemo->getOrder() === null) {
            throw new NoSuchEntityException(__('We found an invalid order to refund.'));
        }

        $order = $creditmemo->getOrder();

        // Core mirror check 3 (CreditmemoService.php:205-218): rounded
        // accumulated refund must not exceed the rounded paid amount.
        $baseOrderRefund = $this->priceCurrency->round(
            (float)$order->getBaseTotalRefunded() + (float)$creditmemo->getBaseGrandTotal()
        );
        if ($baseOrderRefund > $this->priceCurrency->round((float)$order->getBaseTotalPaid())) {
            $baseAvailableRefund = (float)$order->getBaseTotalPaid()
                - (float)$order->getBaseTotalRefunded();

            throw new LocalizedException(
                __(
                    'The most money available to refund is %1.',
                    $order->getBaseCurrency()->formatTxt($baseAvailableRefund)
                )
            );
        }

        // Supplementary online-gateway check (not in core validate): the
        // amount the provider would be asked to refund must be positive.
        if ((float)$creditmemo->getBaseGrandTotal() <= 0) {
            throw new LocalizedException(
                __('Zalopay: The online refund amount must be greater than zero.')
            );
        }
    }
}
