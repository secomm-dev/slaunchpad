<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\ViewModel\Shipment;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Shipment;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;
use Secomm\ShippingCore\Model\Shipment\OfflineEligibilitySession;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — data + gating for the "Create Offline Shipment" control on
 * the admin new-shipment form. Renders only when the order's shipping method resolves to an
 * ENABLED carrier offline capability (capability-driven — ShippingCore never names carriers).
 */
class OfflineControl implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly FulfillmentModeResolver $resolver,
        private readonly OfflineEligibilitySession $eligibilitySession
    ) {
    }

    public function canShow(): bool
    {
        $shipment = $this->registry->registry('current_shipment');

        return $shipment instanceof Shipment
            && $this->resolver->resolveCarrierCode(
                (string) ($shipment->getOrder()?->getShippingMethod() ?? '')
            ) !== null;
    }

    /**
     * The eligibility stashed by the carrier gate when the online attempt was blocked with an
     * offline-eligible reason — pulled + CLEARED so a later form render never shows stale data.
     *
     * @return array{reason_code: string, message: string}|null
     */
    public function pullStashedEligibility(): ?array
    {
        $shipment = $this->registry->registry('current_shipment');
        if (!$shipment instanceof Shipment) {
            return null;
        }

        return $this->eligibilitySession->pull((int) $shipment->getOrderId());
    }
}
