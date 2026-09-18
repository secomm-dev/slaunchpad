<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Plugin\Model\Order;

use Launchpad\MageplazaExtraFeeFix\Service\ExtraFeeOrderHelper;
use Magento\Sales\Model\Order;

class CanCreditmemoPlugin
{
    /**
     * @param ExtraFeeOrderHelper $extraFeeOrderHelper
     */
    public function __construct(
        private readonly ExtraFeeOrderHelper $extraFeeOrderHelper
    ) {
    }

    /**
     * Disable the Credit Memo button if all items and shipping are already
     * fully refunded/canceled and the only remaining unrefunded amount is a
     * non-refundable Mageplaza Extra Fee (rf = 0).
     *
     * If unrefunded shipping still exists the button stays enabled so the
     * admin can still issue a shipping-only credit memo.
     *
     * @param Order $subject
     * @param bool $result
     * @return bool
     */
    public function afterCanCreditmemo(Order $subject, bool $result): bool
    {
        if ($result && (float)$subject->getTotalRefunded() > 0) {
            if ($this->extraFeeOrderHelper->hasNonRefundableExtraFee($subject)
                && $this->extraFeeOrderHelper->areAllItemsRefundedOrCanceled($subject)
            ) {
                // Allow credit memo when shipping was invoiced but not yet fully refunded.
                $shippingInvoiced = (float)$subject->getBaseShippingInvoiced();
                $shippingRefunded = (float)$subject->getBaseShippingRefunded();
                if ($shippingInvoiced > $shippingRefunded) {
                    return true;
                }

                return false;
            }
        }

        return $result;
    }
}
